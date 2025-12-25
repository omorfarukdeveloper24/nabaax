<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Post_media;
use App\Models\VideoView;
use App\Models\PostBoost;
use App\Models\Member;
use App\Models\Follow;
use App\Models\MiniAd;
use App\Services\BoostService;
use App\Models\FollowBoost;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Google\Cloud\Storage\StorageClient;


class PostController extends Controller
{
    function __construct()
    {
        $this->middleware("auth.jwt", [
            
        ]);
    }



//   public function list()
//     {
        
//       $member = Auth::guard("member")->user();
//       if (!$member) {
//         return response()->json([
//             'status' => failed,
//                 'message' => 'Unauthorized user'
//             ], 401);
//         }
        
//      $posts = Post::with('member','media')
//             ->latest()
//             ->paginate(15);
//         return response()->json(['status'=>'success','data'=>$posts]);
//     }


public function testApi() {
    return 'This is our Google cloud last testing Auto Update';
}

   public function prosearch(Request $request)
    {
        $posts = Post::select('id', 'member_id', 'content', 'visibility', 'created_at')
            ->with(['member:id,name,image,username', 'media:id,post_id,media_type,path'])
            ->where('visibility', 'public');
    
        
        if ($request->keyword) {
            $keyword = $request->keyword;
            $posts = $posts->where(function ($query) use ($keyword) {
                $query->where('content', 'LIKE', "%{$keyword}%")
                      ->orWhereHas('member', function ($q) use ($keyword) {
                          $q->where('name', 'LIKE', "%{$keyword}%")
                            ->orWhere('username', 'LIKE', "%{$keyword}%"); 
                      });
            });
        }
    
  
        $posts = $posts->latest()->take(20)->get();
    
       
        if (empty($request->keyword)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please enter a keyword to search.',
                'data' => []
            ]);
        }
    
        return response()->json([
            'status' => 'success',
            'message' => count($posts) > 0 ? 'Posts found successfully.' : 'No posts found.',
            'data' => $posts
        ]);
    }




    
    public function list()
    {
        $member = Auth::guard("member")->user();
    
        if (!$member) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthorized user'
            ], 401);
        }
    
        $memberId = $member->id;
    
        $miniAds = Miniad::where('status', 1)
            ->select('id', 'title', 'image', 'link')
            ->get();
    
        if ($miniAds->isEmpty()) {
            $miniAds = collect([]);
        }
    
        $seed = request()->has('page') ? session()->get('post_seed') : rand(1, 9999);
        if (!request()->has('page')) {
            session()->put('post_seed', $seed);
        }
    
        $posts = Post::with(['member', 'media'])
            ->withCount([
                'likes as like_count' => function ($q) {
                    $q->where('type', 1);
                },
                'likes as dislike_count' => function ($q) {
                    $q->where('type', 2);
                },
                'comments as comment_count'
            ])
            ->withExists([
                'likes as liked_by_me' => function ($q) use ($memberId) {
                    $q->where('member_id', $memberId)->where('type', 1);
                },
                'likes as disliked_by_me' => function ($q) use ($memberId) {
                    $q->where('member_id', $memberId)->where('type', 2);
                },
            ])
            ->whereNotIn('id', function ($query) use ($memberId) {
                $query->select('post_id')
                    ->from('post_views')
                    ->where('member_id', $memberId);
            })
            ->inRandomOrder($seed) 
            ->paginate(15);
    
        $posts->getCollection()->transform(function ($post, $index) use ($memberId, $miniAds) {
            $isFollowing = Follow::where('follower_id', $memberId)
                ->where('following_id', $post->member->id)
                ->exists();
    
            $post->is_following = $isFollowing;
    
            $followBoost = FollowBoost::where('member_id', $post->member->id)->first();
    
            if ($followBoost && $followBoost->status === 'active') {
                $post->follow_boost_status = 'active';
            } else {
                $post->follow_boost_status = 'inactive';
            }
    
            $postBoost = PostBoost::where('post_id', $post->id)->latest()->first();
    
            if ($postBoost && $postBoost->status === 'active') {
                $post->post_boost = [
                    'id' => $postBoost->id,
                    'message_link' => $postBoost->message_link,
                    'website_link' => $postBoost->website_link,
                    'status' => 'active'
                ];
            } else {
                $post->post_boost = [
                    'id' => null,
                    'status' => 'inactive'
                ];
            }
    
            if ($miniAds->count() > 0) {
                $start = ($index * 2) % $miniAds->count();
                $miniAdPair = [];
    
                for ($i = 0; $i < 2; $i++) {
                    $miniAdPair[] = $miniAds[($start + $i) % $miniAds->count()];
                }
    
                $post->mini_ads = $miniAdPair;
            } else {
                $post->mini_ads = [];
            }
    
            return $post;
        });
    
        return response()->json([
            'status' => 'success',
            'data' => $posts
        ]);
    }
    
    
    

    
    
    
    // This code our Correct post code latest version 22 June 2024
    
    
    
    // public function list()
    // {
    //     $member = Auth::guard("member")->user();
    
    //     if (!$member) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Unauthorized user'
    //         ], 401);
    //     }
    
    //     $memberId = $member->id;
    
    //     $miniAds = Miniad::where('status', 1)
    //         ->select('id', 'title', 'image', 'link')
    //         ->get();
    
    //     if ($miniAds->isEmpty()) {
    //         $miniAds = collect([]);
    //     }
    
    //     $posts = Post::with(['member', 'media'])
    //         ->withCount([
    //             'likes as like_count' => function ($q) {
    //                 $q->where('type', 1);
    //             },
    //             'likes as dislike_count' => function ($q) {
    //                 $q->where('type', 2);
    //             },
    //             'comments as comment_count'
    //         ])
    //         ->withExists([
    //             'likes as liked_by_me' => function ($q) use ($memberId) {
    //                 $q->where('member_id', $memberId)->where('type', 1);
    //             },
    //             'likes as disliked_by_me' => function ($q) use ($memberId) {
    //                 $q->where('member_id', $memberId)->where('type', 2);
    //             },
    //         ])
    //         ->whereNotIn('id', function ($query) use ($memberId) {
    //             $query->select('post_id')
    //                 ->from('post_views')
    //                 ->where('member_id', $memberId);
    //         })
    //         ->latest()
    //         ->paginate(10);
            
        
    
    //     $posts->getCollection()->transform(function ($post, $index) use ($memberId, $miniAds) {
    //         $isFollowing = Follow::where('follower_id', $memberId)
    //             ->where('following_id', $post->member->id)
    //             ->exists();
    
    //         $post->is_following = $isFollowing;
            
            
    //         $followBoost = FollowBoost::where('member_id', $post->member->id)->first();
    
    //         if ($followBoost && $followBoost->status === 'active') {
    //             $post->follow_boost_status = 'active';
    //         } else {
    //             $post->follow_boost_status = 'inactive';
    //         }
            
    //         // PostBoost check
    //         $postBoost = PostBoost::where('post_id', $post->id)->latest()->first();
            
        
    //         if ($postBoost && $postBoost->status === 'active') {
    //             $post->post_boost = [
    //                 'id' => $postBoost->id,
    //                 'message_link' => $postBoost->message_link,
    //                 'website_link' => $postBoost->website_link,
    //                 'status' => 'active'
    //             ];
    //         } else {
    //             $post->post_boost = [
    //                 'id' => null,
    //                 'status' => 'inactive'
    //             ];
    //         }
    
    //         if ($miniAds->count() > 0) {
                
    //             $start = ($index * 2) % $miniAds->count();
    //             $miniAdPair = [];
    
    //             for ($i = 0; $i < 2; $i++) {
    //                 $miniAdPair[] = $miniAds[($start + $i) % $miniAds->count()];
    //             }
    
    //             $post->mini_ads = $miniAdPair;
    //         } else {
    //             $post->mini_ads = [];
    //         }
    
    //         return $post;
    //     });
    
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $posts
    //     ]);
    // }










    public function miniads(Request $request)
{
    $member = Auth::guard('member')->user();

    if (!$member) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    $validator = Validator::make($request->all(), [
        'title'  => 'required|string|max:255',
        'image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        'link'   => 'required|max:255',
        'status' => 'required|in:0,1',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors'  => $validator->errors(),
        ], 422);
    }

    $validated = $validator->validated();

    $data = [
        'member_id' => $member->id,
        'title'     => $validated['title'],
        'link'      => $validated['link'] ?? null,
        'status'    => $validated['status'],
        'image'     => null,
    ];

    if ($request->hasFile('image')) {
        try {
            $image = $request->file('image');

            // ১. ফাইল নেম এবং পাথ তৈরি
            $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
            $cleanName = time() . '-' . strtolower(preg_replace('/\s+/', '-', $originalName)) . '.webp';
            $fileName = 'miniads/' . $cleanName;

            // ২. ইমেজ প্রসেসিং (Intervention Image)
            $img = Image::make($image->getRealPath());
            $img->resize(600, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // ইমেজটিকে ওয়েবপি ফরম্যাটে এনকোড করা
            $encodedImage = $img->encode('webp', 80);

            // ৩. গুগল ক্লাউড SDK ব্যবহার করে সরাসরি আপলোড (Uniform Access এর জন্য নিরাপদ)
            $keyFileData = json_decode(file_get_contents(base_path(config('filesystems.disks.gcs.key_file'))), true);
            
            $storage = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile' => $keyFileData,
            ]);

            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            // আপলোড করার সময় কোনো ACL বা Visibility পাঠানো হচ্ছে না
            $object = $bucket->upload($encodedImage->getEncoded(), [
                'name' => $fileName,
                'metadata' => [
                    'contentType' => 'image/webp'
                ]
            ]);

            // ৪. সফল হলে পাবলিক URL সেট করা
            if ($object) {
                $data['image'] = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'System Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ৫. ডাটাবেসে সেভ করা
    $miniad = MiniAd::create($data);

    return response()->json([
        'success' => true,
        'message' => 'Mini Ad uploaded to GCS miniads folder successfully!',
        'url'     => $data['image'],
        'data'    => $miniad
    ]);
}








//     public function miniads(Request $request)
// {
//     $member = Auth::guard('member')->user();

//     if (!$member) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Unauthorized'
//         ], 401);
//     }

//     $validator = Validator::make($request->all(), [
//         'title'  => 'required|string|max:255',
//         'image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
//         'link'   => 'required|max:255',
//         'status' => 'required|in:0,1',
//     ]);

//     if ($validator->fails()) {
//         return response()->json([
//             'success' => false,
//             'errors'  => $validator->errors(),
//         ], 422);
//     }

//     $validated = $validator->validated();

//     $data = [
//         'member_id' => $member->id,
//         'title'     => $validated['title'],
//         'link'      => $validated['link'] ?? null,
//         'status'    => $validated['status'],
//         'image'     => null,
//     ];

//     if ($request->hasFile('image')) {
//         try {
//             $image = $request->file('image');
            
            
//             // ১. ফাইল নেম তৈরি
//             $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
//             $cleanName = time() . '-' . strtolower(preg_replace('/\s+/', '-', $originalName)) . '.webp';
//             $fileName = 'miniads/' . $cleanName;

//             // ২. ইমেজ প্রসেসিং (Intervention Image)
//             $img = Image::make($image->getRealPath());
//             $img->resize(600, null, function ($constraint) {
//                 $constraint->aspectRatio();
//                 $constraint->upsize();
//             });

//             // ইমেজটিকে ওয়েবপি ফরম্যাটে এনকোড করা
//             $encodedImage = $img->encode('webp', 80);

//             // ৩. GCS-এ আপলোড করা (সঠিক পদ্ধতি)
//             // সরাসরি Storage::disk('gcs')->put() ব্যবহার করা হয়েছে যা আপনার এররটি দূর করবে
//             $isUploaded = Storage::disk('gcs')->put($fileName, $encodedImage->getEncoded(), [
//                 'contentType' => 'image/webp'
//             ]);
            
//             // dd($isUploaded);

//             // যদি আপলোড ব্যর্থ হয়
//             if (!$isUploaded) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'GCS storage rejected the file. Please check bucket status.'
//                 ], 500);
//             }

//             // ৪. সফল হলে URL জেনারেট করা
//             $data['image'] = Storage::disk('gcs')->url($fileName);

//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'System Error: ' . $e->getMessage(),
//             ], 500);
//         }
//     }

//     // ৫. ডাটাবেসে সেভ করা
//     $miniad = MiniAd::create($data);

//     return response()->json([
//         'success' => true,
//         'message' => 'Mini Ad uploaded to GCS successfully!',
//         'url'     => $data['image'],
//         'data'    => $miniad
//     ]);
// }



//    public function miniads(Request $request)
// {
//     $member = Auth::guard('member')->user();

//     if (!$member) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Unauthorized'
//         ], 401);
//     }

//     $validator = Validator::make($request->all(), [
//         'title'  => 'required|string|max:255',
//         'image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
//         'link'   => 'required|max:255',
//         'status' => 'required|in:0,1',
//     ]);

//     if ($validator->fails()) {
//         return response()->json([
//             'success' => false,
//             'errors'  => $validator->errors(),
//         ], 422);
//     }

//     $validated = $validator->validated();

//     $data = [
//         'member_id' => $member->id,
//         'title'     => $validated['title'],
//         'link'      => $validated['link'] ?? null,
//         'status'    => $validated['status'],
//     ];

//     if ($request->hasFile('image')) {
//         try {
//             $image = $request->file('image');
            
//             // ১. ফাইল নেম তৈরি
//             $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
//             $cleanName = time() . '-' . strtolower(preg_replace('/\s+/', '-', $originalName)) . '.webp';
//             $fileName = 'miniads/' . $cleanName;

//             // ২. ইমেজ প্রসেসিং (Intervention Image)
//             $img = Image::make($image->getRealPath());
//             $img->resize(600, null, function ($constraint) {
//                 $constraint->aspectRatio();
//                 $constraint->upsize();
//             })->encode('webp', 80);

//             // ৩. GCS-এ আপলোড করা (stream এর পরিবর্তে সরাসরি স্ট্রিং ডেটা ব্যবহার)
//             // অনেক সময় stream() GCS ড্রাইভারের সাথে সমস্যা করে, তাই __toString() বা detach() নিরাপদ
//             Storage::disk('gcs')->put($fileName, (string) $img, [
//                 'visibility' => 'public',
//                 'contentType' => 'image/webp'
//             ]);

//             // ৪. পূর্ণাঙ্গ URL তৈরি করা
//             $data['image'] = Storage::disk('gcs')->url($fileName);

//         } catch (\Exception $e) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'File upload failed: ' . $e->getMessage()
//             ], 500);
//         }
//     }

//     // ৫. ডাটাবেসে সেভ করা
//     $miniad = MiniAd::create($data);

//     return response()->json([
//         'success' => true,
//         'message' => 'Mini Ad uploaded to GCS successfully!',
//         'url'     => $data['image'],
//         'data'    => $miniad
//     ]);
// }




    
    
    // public function miniads(Request $request)
    // {
    //     $member = Auth::guard('member')->user();

    //     if (!$member) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized'
    //         ], 401);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'title'  => 'required|string|max:255',
    //         'image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
    //         'link'   => 'required|max:255',
    //         'status' => 'required|in:0,1',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'errors'  => $validator->errors(),
    //         ], 422);
    //     }

    //     $validated = $validator->validated();

    //     $data = [
    //         'member_id' => $member->id,
    //         'title'     => $validated['title'],
    //         'link'      => $validated['link'] ?? null,
    //         'status'    => $validated['status'],
    //     ];

    //     if ($request->hasFile('image')) {
    //         $image = $request->file('image');
            
    //         // ১. নাম তৈরি করা
    //         $name = time() . '-' . strtolower(preg_replace('/\s+/', '-', $image->getClientOriginalName()));
    //         $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name);
    //         $fileName = 'miniads/' . $name;

    //         // ২. ইমেজ ইন্টারভেনশন দিয়ে প্রসেসিং করা
    //         $targetWidth = 600;
    //         $img = Image::make($image->getRealPath());
            
    //         $img->resize($targetWidth, null, function ($constraint) {
    //             $constraint->aspectRatio();
    //             $constraint->upsize();
    //         })->encode('webp', 80); // WebP ফরম্যাটে ৮০% কোয়ালিটিতে কনভার্ট

    //         // ৩. সরাসরি GCS বাকেটে আপলোড করা
    //         // এখানে $img->stream() ব্যবহার করা হয়েছে যেন লোকাল সার্ভারে ফাইল সেভ না করতে হয়
    //         Storage::disk('gcs')->put($fileName, $img->stream(), 'public');

    //         // ৪. ডাটাবেসে GCS এর ফুল URL অথবা পাথ সেভ করা
    //         $data['image'] = Storage::disk('gcs')->url($fileName);
    //     }

        

    //     $miniad = MiniAd::create($data);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Mini Ad uploaded to GCS successfully!',
    //         'url'     => $data['image'],
    //         'data'    => $miniad
    //     ]);
    // }
    
    
   
    // public function miniads(Request $request)
    // {
    //     return "We ar Successfully updated google cloud build auto deploy testing success"; 

    //     return "Not OKK";

    //     $member = Auth::guard('member')->user();
    
    //     if (!$member) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized'
    //         ], 401);
    //     }
    
    //     $validator = Validator::make($request->all(), [
    //         'title'  => 'required|string|max:255',
    //         'image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
    //         'link'   => 'required|max:255',
    //         'status' => 'required|in:0,1',
    //     ]);
    
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'errors'  => $validator->errors(),
    //         ], 422);
    //     }
    
    //     $validated = $validator->validated();
    
    //     $data = [
    //         'member_id' => $member->id,
    //         'title'     => $validated['title'],
    //         'link'      => $validated['link'] ?? null,
    //         'status'    => $validated['status'],
    //     ];
    
    //     if ($request->hasFile('image')) {
    //         $image = $request->file('image');
    
    //         $uploadPath = public_path('uploads/miniads/');
    //         if (!file_exists($uploadPath)) {
    //             mkdir($uploadPath, 0777, true);
    //         }
    

    //         $name = time() . '-' . strtolower(preg_replace('/\s+/', '-', $image->getClientOriginalName()));
    //         $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name);
    
    //         $tempPath = $uploadPath . 'temp_' . $name;
    //         $finalPath = $uploadPath . $name;
    

    //         $targetWidth = 600;
    //         $img = Image::make($image->getRealPath());
    //         $originalWidth = $img->width();
    //         $originalHeight = $img->height();
    //         $ratio = $originalHeight / $originalWidth;
    //         $targetHeight = intval($targetWidth * $ratio);
    
    //         $img->resize($targetWidth, null, function ($constraint) {
    //             $constraint->aspectRatio();
    //             $constraint->upsize();
    //         });
    
    //         $img->resizeCanvas($targetWidth, $targetHeight, 'center', false, '#ffffff');
    
    //         $quality = 90;
    //         do {
    //             $img->encode('webp', $quality)->save($tempPath);
    //             $size = filesize($tempPath) / 1024 / 1024; // in MB
    //             $quality -= 5;
    //         } while ($size > 2 && $quality >= 10);
    

    //         if (file_exists($tempPath)) {
    //             rename($tempPath, $finalPath);
    //         }
    
            
    //         $data['image'] = 'public/uploads/miniads/' . $name;
    //     }
    
   
    //     $miniad = MiniAd::create($data);
    
    //     return response()->json([
    //         'success' => 'success',
    //         'message' => 'Mini Ad created successfully!',
    //         'data'    => $miniad
    //     ]);
    // }
    
    // public function postvideo()
    // {
    //     $member = Auth::guard("member")->user();
    
    //     if (!$member) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Unauthorized user'
    //         ], 401);
    //     }
    
    //     $memberId = $member->id;
    
    //     $posts = Post::with(['member', 'media' => function ($q) {
    //                 $q->where('media_type', 'video'); 
    //             }])
    //             ->whereHas('media', function ($q) {
    //                 $q->where('media_type', 'video'); 
    //             })
    //             ->withCount([
    //                 'likes as like_count' => function ($q) {
    //                     $q->where('type', 1);
    //                 },
    //                 'likes as dislike_count' => function ($q) {
    //                     $q->where('type', 2);
    //                 },
    //             ])
    //             ->withExists([
    //                 'likes as liked_by_me' => function ($q) use ($memberId) {
    //                     $q->where('member_id', $memberId)->where('type', 1);
    //                 },
    //                 'likes as disliked_by_me' => function ($q) use ($memberId) {
    //                     $q->where('member_id', $memberId)->where('type', 2);
    //                 },
    //             ])
    //             ->whereNotIn('id', function ($query) use ($memberId) {
    //                 $query->select('post_id')
    //                     ->from('post_views')
    //                     ->where('member_id', $memberId);
    //             })
    //             ->latest()
    //             ->paginate(15);
    
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $posts
    //     ]);
    // }
    
    // public function postvideo()
    // {
    //     $member = Auth::guard('member')->user();
    
    //     if (!$member) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Unauthorized user'
    //         ], 401);
    //     }
    
    //     $memberId = $member->id;
    
    //     $videos = Post_media::with([
    //             'post' => function ($q) use ($memberId) {
    //                 $q->with(['member'])
    //                     ->withCount([
    //                         'likes as like_count' => function ($q) {
    //                             $q->where('type', 1);
    //                         },
    //                         'likes as dislike_count' => function ($q) {
    //                             $q->where('type', 2);
    //                         },
    //                     ])
    //                     ->withExists([
    //                         'likes as liked_by_me' => function ($q) use ($memberId) {
    //                             $q->where('member_id', $memberId)->where('type', 1);
    //                         },
    //                         'likes as disliked_by_me' => function ($q) use ($memberId) {
    //                             $q->where('member_id', $memberId)->where('type', 2);
    //                         },
    //                     ]);
    //             }
    //         ])
    //         ->where('media_type', 'video') // ✅ শুধু ভিডিও মিডিয়া
    //         ->whereHas('post', function ($q) {
    //             $q->where('visibility', 'public');
    //         })
    //         ->latest()
    //         ->paginate(15);
    
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $videos
    //     ]);
    // }
    
    
    
    
    
    
    
    
    
    public function postvideo()
    {
        $member = Auth::guard('member')->user();
        if (!$member) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthorized user'
            ], 401);
        }
    
        $memberId = $member->id;
    
        $watchedVideos = VideoView::where('member_id', $memberId)
            ->pluck('post_media_id')
            ->toArray();
    
        $videos = Post_media::with([
                'post' => function ($q) use ($memberId) {
                    $q->select('id', 'member_id', 'content', 'visibility', 'created_at')
                        ->with(['member'])
                        ->withCount([
                            'likes as like_count' => fn($q) => $q->where('type', 1),
                            'likes as dislike_count' => fn($q) => $q->where('type', 2),
                            'comments as comment_count',
                        ])
                        ->withExists([
                            'likes as liked_by_me' => fn($q) => $q->where('member_id', $memberId)->where('type', 1),
                            'likes as disliked_by_me' => fn($q) => $q->where('member_id', $memberId)->where('type', 2),
                        ]);
                }
            ])
            ->where('media_type', 'video')
            ->whereHas('post', fn($q) => $q->where('visibility', 'public'))
            ->whereNotIn('id', $watchedVideos)
            ->latest()
            ->paginate(5);
    
        $miniads = MiniAd::where('status', 1)->get();
        $miniCount = $miniads->count();
    
        $videos->getCollection()->transform(function ($video, $index) use ($miniads, $miniCount) {
            $firstIndex = ($index * 2) % $miniCount;
            $secondIndex = ($firstIndex + 1) % $miniCount;
    
            $video->mini_ads = [
                $miniads[$firstIndex],
                $miniads[$secondIndex],
            ];
    
            return $video;
        });
    
        return response()->json([
            'status' => 'success',
            'data' => $videos
        ]);
    }
    
    // public function postvideo()
    // {
    //     $member = Auth::guard('member')->user();

    //     if (!$member) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Unauthorized user'
    //         ], 401);
    //     }

    //     $memberId = $member->id;

    //     $watchedVideos = VideoView::where('member_id', $memberId)
    //         ->pluck('post_media_id')
    //         ->toArray();

    //     $videos = Post_media::with([
    //             'post' => function ($q) use ($memberId) {
    //                 $q->select('id', 'member_id', 'content', 'visibility', 'created_at')
    //                     ->with(['member'])
    //                     ->withCount([
    //                         'likes as like_count' => fn($q) => $q->where('type', 1),
    //                         'likes as dislike_count' => fn($q) => $q->where('type', 2),
    //                     ])
    //                     ->withExists([
    //                         'likes as liked_by_me' => fn($q) => $q->where('member_id', $memberId)->where('type', 1),
    //                         'likes as disliked_by_me' => fn($q) => $q->where('member_id', $memberId)->where('type', 2),
    //                     ]);
    //             }
    //         ])
    //         ->where('media_type', 'video')
    //         ->whereHas('post', fn($q) => $q->where('visibility', 'public'))
    //         ->whereNotIn('id', $watchedVideos)
    //         ->latest()
    //         ->paginate(15);

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $videos
    //     ]);
    // }

    
    
    
    

    public function store(Request $request)
    {
     
        
        // return $request->all();
        
        
        $request->validate([
            // 'member_id' => 'required',
            'content' => 'nullable|string',
            'visibility' => 'required',
            'scheduled_at' => 'nullable',
        ]);
        
        
        $member = Auth::guard("member")->user();
        
       if (!$member) {
        return response()->json([
            'status' => failed,
                'message' => 'Unauthorized user'
            ], 401);
        }

        // return $member;
        
        $post = Post::create([
            'member_id' => $member->id,
            'content' => $request->content ?? null,
            'boost_status' => $request->boost_status ?? 0,
            'visibility' => $request->visibility,
            'is_pinned' => $request->is_pinned ?? false,
            'scheduled_at' => $request->scheduled_at,
            
        ]);
        
        // return $member;
        
        if ($request->hasFile('media')) {
            $uploadImagePath = public_path('uploads/post/images/');
            $uploadVideoPath = public_path('uploads/post/videos/');
        
            if (!file_exists($uploadImagePath)) mkdir($uploadImagePath, 0777, true);
            if (!file_exists($uploadVideoPath)) mkdir($uploadVideoPath, 0777, true);
        
            foreach ($request->file('media') as $file) {
        
                $extension = strtolower($file->getClientOriginalExtension());
                $name = time() . '-' . strtolower(preg_replace('/\s+/', '-', $file->getClientOriginalName()));
        
                $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        
                if (in_array($extension, $imageExtensions)) {
                    $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name);
                    $imageUrl = $uploadImagePath . $name;
        
                    $targetWidth = 600;
                    $img = Image::make($file->getRealPath());
                    $img->resize($targetWidth, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
        
                    $quality = 90;
                    do {
                        $tempPath = $uploadImagePath . 'temp_' . $name;
                        $img->encode('webp', $quality)->save($tempPath);
                        $size = filesize($tempPath) / 1024 / 1024;
                        $quality -= 5;
                    } while ($size > 2 && $quality >= 10);
        
                    rename($tempPath, $imageUrl);
        
                    Post_media::create([
                        'post_id' => $post->id,
                        'media_type' => 'image',
                        'path' => 'public/uploads/post/images/' . $name,
                    ]);
                } elseif (in_array($extension, $videoExtensions)) {
                    $videoUrl = $uploadVideoPath . $name;
                    $file->move($uploadVideoPath, $name);
        
                    Post_media::create([
                        'post_id' => $post->id,
                        'media_type' => 'video',
                        'path' => 'public/uploads/post/videos/' . $name,
                    ]);
                }
            }
        }


        
        
        // if ($request->hasFile('images')) {
            
        //     foreach ($request->file('images') as $image) {
        //         $name = time() . '-' . $image->getClientOriginalName();
        //         $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name);
        //         $name = strtolower(preg_replace('/\s+/', '-', $name));
        //         $uploadPath = 'public/uploads/post/images';
        //         $imageUrl = $uploadPath . $name;

        //         $targetWidth = 600;
        //         $img = Image::make($image->getRealPath());
        //         $originalWidth = $img->width();
        //         $originalHeight = $img->height();
        //         $ratio = $originalHeight / $originalWidth;
        //         $targetHeight = intval($targetWidth * $ratio);

        //         $img->resize($targetWidth, null, function ($constraint) {
        //             $constraint->aspectRatio();
        //             $constraint->upsize();
        //         });
        //         $img->resizeCanvas($targetWidth, $targetHeight, 'center', false, '#ffffff');
        //         $quality = 90;

        //         do {
        //             $tempPath = $uploadPath . 'temp_' . $name;
        //             $img->encode('webp', $quality)->save($tempPath);
        //             $size = filesize($tempPath) / 1024 / 1024;
        //             $quality -= 5;
        //         } while ($size > 2 && $quality >= 10);

        //         rename($tempPath, $imageUrl);

                
        //         Post_media::create([
        //             'post_id' => $post->id,
        //             'media_type' => 'image',
        //             'path' => $imageUrl,
        //         ]);
        //     }
        // }



        // if ($request->hasFile('videos')) {
        //     foreach ($request->file('videos') as $video) {
        //         $name = time() . '-' . $video->getClientOriginalName();
        //         $name = strtolower(preg_replace('/\s+/', '-', $name));
        //         $uploadPath = 'public/uploads/post/videos/';
        //         $videoUrl = $uploadPath . $name;

        //         $video->move(public_path($uploadPath), $name);

        //         Post_media::create([
        //             'post_id' => $post->id,
        //             'media_type' => 'video',
        //             'path' => $videoUrl,
        //         ]);
        //     }
        // }

     
        
        
          
        if ($request->boost_status == 1) {
            
            
           
            if ($request->boost_status == 1 && BoostService::hasActiveBoost($member->id)) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'You already have an active boost!'
                ], 403);
            }
        
            if ($request->boost_status == 1 && $member->balance < $request->amount) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Not enough balance'
                ], 403);
            }
        
            
            
            
        $member->balance -= $request->amount;
        $member->save();

        $postboost = PostBoost::create([
                'post_id'     => $post->id,  
                'member_id'   => $member->id,
                'boost_amount'     => $request->amount,
                'remaining_amount' => $request->amount, 
                'message_link'     => $request->message_link,
                'website_link'     => $request->website_link,
                'age_from'    => $request->age_from,
                'age_to'      => $request->age_to,
                'start_date'  => Carbon::now(),
                'end_date'    => $request->end_date ? Carbon::parse($request->end_date)->format('Y-m-d') : null,
                'gender'      => $request->gender,
                'location'    => $request->location,
                'profession'  => $request->profession,
                'income_range'=> $request->income_range,
                'click_cost'  => '10',
                'status'           => 'active',
            ]);
        }

        $post->load(['boost', 'media']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Post created successfully!',
            'post'    => $post,
        ]);
    }
    
   
    
    public function linkClick($boostId)
    {
        $boost = PostBoost::findOrFail($boostId);
        BoostService::deductClickCost($boost);
        
        return response()->json([
            'message'    => 'Click counted successfully',
            'remaining'  => $boost->remaining_amount,
            'status'     => $boost->status
        ]);
    }
    
    
    
    
    
    

    public function details($id)
    {
        $post = Post::with('member','media')->find($id);

        if (!$post) {
            return response()->json(['status' => 'Failed', 'message' => 'Post not found'], 404);
        }

        return response()->json($post);
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    public function update(Request $request)
    {
        return "ok";
        $member = Auth::guard("member")->user();
    
        if (!$member) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthorized user'
            ], 401);
        }
    
        $post = Post::where('id', $id)->where('member_id', $member->id)->first();
    
        if (!$post) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Post not found or not authorized'
            ], 404);
        }
    
        $request->validate([
            'content' => 'nullable|string',
            'visibility' => 'nullable|in:public,private,friends', 
            'scheduled_at' => 'nullable|date',
            'boost_status' => 'nullable|in:0,1',
            'media.*' => 'nullable|file|max:10240', 
        ]);
    
        $post->update([
            'content' => $request->content ?? $post->content,
            'visibility' => $request->visibility ?? $post->visibility,
            'is_pinned' => $request->is_pinned ?? $post->is_pinned,
            'scheduled_at' => $request->scheduled_at ?? $post->scheduled_at,
            'boost_status' => $request->boost_status ?? $post->boost_status,
        ]);
    
        if ($request->remove_old_media && $request->remove_old_media == true) {
            foreach ($post->media as $oldMedia) {
                $filePath = public_path(str_replace('public/', '', $oldMedia->path));
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $oldMedia->delete();
            }
        }
    
        if ($request->hasFile('media')) {
            $uploadImagePath = public_path('uploads/post/images/');
            $uploadVideoPath = public_path('uploads/post/videos/');
    
            if (!file_exists($uploadImagePath)) mkdir($uploadImagePath, 0777, true);
            if (!file_exists($uploadVideoPath)) mkdir($uploadVideoPath, 0777, true);
    
            foreach ($request->file('media') as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $name = time() . '-' . strtolower(preg_replace('/\s+/', '-', $file->getClientOriginalName()));
    
                $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
    
                
                if (in_array($extension, $imageExtensions)) {
                    $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name);
                    $imageUrl = $uploadImagePath . $name;
    
                    $targetWidth = 600;
                    $img = Image::make($file->getRealPath());
                    $img->resize($targetWidth, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
    
                    $quality = 90;
                    do {
                        $tempPath = $uploadImagePath . 'temp_' . $name;
                        $img->encode('webp', $quality)->save($tempPath);
                        $size = filesize($tempPath) / 1024 / 1024;
                        $quality -= 5;
                    } while ($size > 2 && $quality >= 10);
    
                    rename($tempPath, $imageUrl);
    
                    Post_media::create([
                        'post_id' => $post->id,
                        'media_type' => 'image',
                        'path' => 'public/uploads/post/images/' . $name,
                    ]);
                }
    
                
                elseif (in_array($extension, $videoExtensions)) {
                    $videoUrl = $uploadVideoPath . $name;
                    $file->move($uploadVideoPath, $name);
    
                    Post_media::create([
                        'post_id' => $post->id,
                        'media_type' => 'video',
                        'path' => 'public/uploads/post/videos/' . $name,
                    ]);
                }
            }
        }
    
       
        if ($request->boost_status == 1) {
            PostBoost::updateOrCreate(
                ['post_id' => $post->id],
                [
                    'member_id'   => $member->id,
                    'age_from'    => $request->age_from,
                    'age_to'      => $request->age_to,
                    'start_date'  => Carbon::now(),
                    'end_date'    => $request->end_date ? Carbon::parse($request->end_date)->format('Y-m-d') : null,
                    'gender'      => $request->gender,
                    'location'    => $request->location,
                    'profession'  => $request->profession,
                    'income_range'=> $request->income_range,
                ]
            );
        }
    
        $post->load(['boost', 'media']);
    
        return response()->json([
            'status'  => 'success',
            'message' => 'Post updated successfully!',
            'post'    => $post,
        ]);
    }


   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   

    public function destroy($id)
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json(['status' => false, 'message' => 'Post not found'], 404);
        }

        $post->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Post deleted successfully!',
        ]);
    }
}
