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
        'image'  => 'required|image|mimes:jpg,jpeg,png,webp',
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

            $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
            $cleanName = time() . '-' . strtolower(preg_replace('/\s+/', '-', $originalName)) . '.webp';
            $fileName = 'miniads/' . $cleanName;

            $img = Image::make($image->getRealPath());

            $img->orientate(); 

            $encodedImage = $img->encode('webp', 80);

            $keyFileData = config('filesystems.disks.gcs.key_file');
            if (!is_array($keyFileData)) {
                $keyFileData = json_decode(file_get_contents(base_path($keyFileData)), true);
            }
            
            $storage = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile' => $keyFileData,
            ]);

            $bucketName = config('filesystems.disks.gcs.bucket');
            $bucket = $storage->bucket($bucketName);

            $object = $bucket->upload($encodedImage->getEncoded(), [
                'name' => $fileName,
                'metadata' => [
                    'contentType' => 'image/webp'
                ]
            ]);

            if ($object) {
                $data['image'] = "https://storage.googleapis.com/" . $bucketName . "/" . $fileName;
            }

        } catch (\Exception $e) {
            \Log::error("GCS Upload Error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    $miniad = MiniAd::create($data);

    return response()->json([
        'success' => true,
        'message' => 'Mini Ad uploaded successfully!',
        'url'     => $data['image'],
        'data'    => $miniad
    ]);
}








    
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
        $request->validate([
            'content' => 'nullable|string',
            'visibility' => 'required',
            'media.*' => 'nullable|file',
        ]);

        $member = Auth::guard("member")->user();
        if (!$member) return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);

        // ১. পোস্ট তৈরি
        $post = Post::create([
            'member_id' => $member->id,
            'content' => $request->content ?? null,
            'boost_status' => $request->boost_status ?? 0,
            'visibility' => $request->visibility,
            'is_pinned' => $request->is_pinned ?? false,
            'scheduled_at' => $request->scheduled_at,
        ]);

        if ($request->hasFile('media')) {
            try {
                // GCS কনফিগারেশন
                $keyFileData = config('filesystems.disks.gcs.key_file');
                if (!is_array($keyFileData)) $keyFileData = json_decode(file_get_contents(base_path($keyFileData)), true);
                
                $storage = new StorageClient(['projectId' => config('filesystems.disks.gcs.project_id'), 'keyFile' => $keyFileData]);
                $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

                foreach ($request->file('media') as $file) {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                    $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];

                    // --- ইমেজ প্রসেসিং (১২০০পিএক্স এবং ৫০০ কেবি লিমিট) ---
                    if (in_array($extension, $imageExtensions)) {
                        $img = Image::make($file->getRealPath());
                        
                        // রেজুলেশন ঠিক রেখে ১২০০ পিক্সেল চওড়া করা
                        $img->resize(1200, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });

                        // কোয়ালিটি কমিয়ে ৫০০ কেবি এর নিচে আনা
                        $quality = 85; 
                        $encoded = $img->encode('webp', $quality);
                        
                        while (strlen($encoded) / 1024 > 500 && $quality > 10) {
                            $quality -= 5;
                            $encoded = $img->encode('webp', $quality);
                        }

                        $fileName = 'posts/images/' . time() . '-' . uniqid() . '.webp';
                        $bucket->upload($encoded, [
                            'name' => $fileName, 
                            'metadata' => ['contentType' => 'image/webp']
                        ]);

                        Post_media::create([
                            'post_id' => $post->id,
                            'media_type' => 'image',
                            'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
                        ]);

                    // --- ভিডিও প্রসেসিং (সরাসরি আপলোড) ---
                    } elseif (in_array($extension, $videoExtensions)) {
                        $fileName = 'posts/videos/' . time() . '-' . uniqid() . '.' . $extension;
                        
                        // ভিডিও কোনো পরিবর্তন ছাড়াই সরাসরি আপলোড হচ্ছে
                        $bucket->upload(fopen($file->getRealPath(), 'r'), [
                            'name' => $fileName,
                            'metadata' => ['contentType' => $file->getMimeType()]
                        ]);

                        Post_media::create([
                            'post_id' => $post->id,
                            'media_type' => 'video',
                            'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Media Upload Error: " . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success', 
            'message' => 'Post created successfully!', 
            'post' => $post->load('media')
        ]);
    }

    
    


    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'content' => 'nullable|string',
    //         'visibility' => 'required',
    //         'scheduled_at' => 'nullable',
    //         'media.*' => 'nullable|file|max:51200', // max 50MB
    //     ]);

    //     $member = Auth::guard("member")->user();
        
    //     if (!$member) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Unauthorized user'
    //         ], 401);
    //     }

    //     // বুস্ট চেক (যদি বুস্ট এনাবল থাকে)
    //     if ($request->boost_status == 1) {
    //         if (BoostService::hasActiveBoost($member->id)) {
    //             return response()->json([
    //                 'status' => 'failed',
    //                 'message' => 'You already have an active boost!'
    //             ], 403);
    //         }

    //         if ($member->balance < $request->amount) {
    //             return response()->json([
    //                 'status' => 'failed',
    //                 'message' => 'Not enough balance'
    //             ], 403);
    //         }
    //     }

    //     // ১. পোস্ট ক্রিয়েট করা
    //     $post = Post::create([
    //         'member_id' => $member->id,
    //         'content' => $request->content ?? null,
    //         'boost_status' => $request->boost_status ?? 0,
    //         'visibility' => $request->visibility,
    //         'is_pinned' => $request->is_pinned ?? false,
    //         'scheduled_at' => $request->scheduled_at,
    //     ]);

    //     // ২. মিডিয়া আপলোড প্রসেস (GCS)
    //     if ($request->hasFile('media')) {
    //         try {
    //             // GCS কনফিগারেশন
    //             $keyFileData = config('filesystems.disks.gcs.key_file');
    //             if (!is_array($keyFileData)) {
    //                 $keyFileData = json_decode(file_get_contents(base_path($keyFileData)), true);
    //             }

    //             $storage = new StorageClient([
    //                 'projectId' => config('filesystems.disks.gcs.project_id'),
    //                 'keyFile' => $keyFileData,
    //             ]);
    //             $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

    //             foreach ($request->file('media') as $file) {
    //                 $extension = strtolower($file->getClientOriginalExtension());
    //                 $cleanName = time() . '-' . uniqid() . '-' . strtolower(preg_replace('/\s+/', '-', $file->getClientOriginalName()));
                    
    //                 $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    //                 $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];

    //                 if (in_array($extension, $imageExtensions)) {
    //                     // ইমেজ প্রসেসিং
    //                     $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $cleanName);
    //                     $img = Image::make($file->getRealPath())->resize(800, null, function ($constraint) {
    //                         $constraint->aspectRatio();
    //                         $constraint->upsize();
    //                     })->encode('webp', 80);

    //                     $filePath = 'posts/images/' . $name;
    //                     $bucket->upload($img->getEncoded(), [
    //                         'name' => $filePath,
    //                         'metadata' => ['contentType' => 'image/webp']
    //                     ]);

    //                     Post_media::create([
    //                         'post_id' => $post->id,
    //                         'media_type' => 'image',
    //                         'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $filePath,
    //                     ]);

    //                 } elseif (in_array($extension, $videoExtensions)) {
    //                     // ভিডিও সরাসরি আপলোড
    //                     $filePath = 'posts/videos/' . $cleanName;
    //                     $bucket->upload(fopen($file->getRealPath(), 'r'), [
    //                         'name' => $filePath,
    //                         'metadata' => ['contentType' => $file->getMimeType()]
    //                     ]);

    //                     Post_media::create([
    //                         'post_id' => $post->id,
    //                         'media_type' => 'video',
    //                         'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $filePath,
    //                     ]);
    //                 }
    //             }
    //         } catch (\Exception $e) {
    //             Log::error("Post Media Upload Error: " . $e->getMessage());
    //         }
    //     }

    //     // ৩. বুস্ট সার্ভিস লজিক
    //     if ($request->boost_status == 1) {
    //         $member->balance -= $request->amount;
    //         $member->save();

    //         PostBoost::create([
    //             'post_id'         => $post->id,  
    //             'member_id'       => $member->id,
    //             'boost_amount'     => $request->amount,
    //             'remaining_amount' => $request->amount, 
    //             'message_link'     => $request->message_link,
    //             'website_link'     => $request->website_link,
    //             'age_from'        => $request->age_from,
    //             'age_to'          => $request->age_to,
    //             'start_date'      => Carbon::now(),
    //             'end_date'        => $request->end_date ? Carbon::parse($request->end_date)->format('Y-m-d') : null,
    //             'gender'          => $request->gender,
    //             'location'        => $request->location,
    //             'profession'      => $request->profession,
    //             'income_range'    => $request->income_range,
    //             'click_cost'      => '10',
    //             'status'          => 'active',
    //         ]);
    //     }

    //     $post->load(['boost', 'media']);

    //     return response()->json([
    //         'status'  => 'success',
    //         'message' => 'Post created successfully!',
    //         'post'    => $post,
    //     ]);
    // }
    
    

    // public function store(Request $request)
    // {
     
        
    //     // return $request->all();
        
        
    //     $request->validate([
    //         // 'member_id' => 'required',
    //         'content' => 'nullable|string',
    //         'visibility' => 'required',
    //         'scheduled_at' => 'nullable',
    //     ]);
        
        
    //     $member = Auth::guard("member")->user();
        
    //    if (!$member) {
    //     return response()->json([
    //         'status' => failed,
    //             'message' => 'Unauthorized user'
    //         ], 401);
    //     }

    //     // return $member;
        
    //     $post = Post::create([
    //         'member_id' => $member->id,
    //         'content' => $request->content ?? null,
    //         'boost_status' => $request->boost_status ?? 0,
    //         'visibility' => $request->visibility,
    //         'is_pinned' => $request->is_pinned ?? false,
    //         'scheduled_at' => $request->scheduled_at,
            
    //     ]);
        
    //     // return $member;
        
    //     if ($request->hasFile('media')) {
    //         $uploadImagePath = public_path('uploads/post/images/');
    //         $uploadVideoPath = public_path('uploads/post/videos/');
        
    //         if (!file_exists($uploadImagePath)) mkdir($uploadImagePath, 0777, true);
    //         if (!file_exists($uploadVideoPath)) mkdir($uploadVideoPath, 0777, true);
        
    //         foreach ($request->file('media') as $file) {
        
    //             $extension = strtolower($file->getClientOriginalExtension());
    //             $name = time() . '-' . strtolower(preg_replace('/\s+/', '-', $file->getClientOriginalName()));
        
    //             $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    //             $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        
    //             if (in_array($extension, $imageExtensions)) {
    //                 $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name);
    //                 $imageUrl = $uploadImagePath . $name;
        
    //                 $targetWidth = 600;
    //                 $img = Image::make($file->getRealPath());
    //                 $img->resize($targetWidth, null, function ($constraint) {
    //                     $constraint->aspectRatio();
    //                     $constraint->upsize();
    //                 });
        
    //                 $quality = 90;
    //                 do {
    //                     $tempPath = $uploadImagePath . 'temp_' . $name;
    //                     $img->encode('webp', $quality)->save($tempPath);
    //                     $size = filesize($tempPath) / 1024 / 1024;
    //                     $quality -= 5;
    //                 } while ($size > 2 && $quality >= 10);
        
    //                 rename($tempPath, $imageUrl);
        
    //                 Post_media::create([
    //                     'post_id' => $post->id,
    //                     'media_type' => 'image',
    //                     'path' => 'public/uploads/post/images/' . $name,
    //                 ]);
    //             } elseif (in_array($extension, $videoExtensions)) {
    //                 $videoUrl = $uploadVideoPath . $name;
    //                 $file->move($uploadVideoPath, $name);
        
    //                 Post_media::create([
    //                     'post_id' => $post->id,
    //                     'media_type' => 'video',
    //                     'path' => 'public/uploads/post/videos/' . $name,
    //                 ]);
    //             }
    //         }
    //     }
    
        
        
          
    //     if ($request->boost_status == 1) {
            
            
           
    //         if ($request->boost_status == 1 && BoostService::hasActiveBoost($member->id)) {
    //             return response()->json([
    //                 'status' => 'failed',
    //                 'message' => 'You already have an active boost!'
    //             ], 403);
    //         }
        
    //         if ($request->boost_status == 1 && $member->balance < $request->amount) {
    //             return response()->json([
    //                 'status' => 'failed',
    //                 'message' => 'Not enough balance'
    //             ], 403);
    //         }
        
            
            
            
    //     $member->balance -= $request->amount;
    //     $member->save();

    //     $postboost = PostBoost::create([
    //             'post_id'     => $post->id,  
    //             'member_id'   => $member->id,
    //             'boost_amount'     => $request->amount,
    //             'remaining_amount' => $request->amount, 
    //             'message_link'     => $request->message_link,
    //             'website_link'     => $request->website_link,
    //             'age_from'    => $request->age_from,
    //             'age_to'      => $request->age_to,
    //             'start_date'  => Carbon::now(),
    //             'end_date'    => $request->end_date ? Carbon::parse($request->end_date)->format('Y-m-d') : null,
    //             'gender'      => $request->gender,
    //             'location'    => $request->location,
    //             'profession'  => $request->profession,
    //             'income_range'=> $request->income_range,
    //             'click_cost'  => '10',
    //             'status'           => 'active',
    //         ]);
    //     }

    //     $post->load(['boost', 'media']);

    //     return response()->json([
    //         'status'  => 'success',
    //         'message' => 'Post created successfully!',
    //         'post'    => $post,
    //     ]);
    // }
    
   
    
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
