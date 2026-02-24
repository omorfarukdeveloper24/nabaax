<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Post_media;
use App\Models\VideoView;
use App\Models\PostView;
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
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\VideoIntelligence\V1\VideoIntelligenceServiceClient;
use App\Traits\NotificationTrait;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class PostController extends Controller
{
    use NotificationTrait;
    function __construct()
    {
        $this->middleware("auth.jwt", [
            
        ]);
    }

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
            ->select('id', 'image')
            ->get();

        if ($miniAds->isEmpty()) {
            $miniAds = collect([]);
        }

        $seed = request()->has('page') ? session()->get('post_seed') : rand(1, 9999);
        if (!request()->has('page')) {
            session()->put('post_seed', $seed);
        }

        $posts = Post::select('id', 'member_id', 'content', 'visibility', 'created_at', 'total_views', 'status')
            ->where('status', 'active') 
            ->with([
                'member' => function($q) {
                    $q->select('id', 'name', 'image');
                },
                'media' => function($q) {
                    $q->select('id', 'post_id', 'media_type', 'path', 'duration');
                }
            ])
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
            ->inRandomOrder($seed) 
            ->paginate(5);

        $posts->getCollection()->transform(function ($post, $index) use ($memberId, $miniAds) {

            $isFollowing = Follow::where('follower_id', $memberId)
                ->where('following_id', $post->member_id)
                ->exists();

            $post->is_following = $isFollowing;

            if ($miniAds->count() > 0) {
                $adIndex = $index % $miniAds->count();
                $post->mini_ads = $miniAds[$adIndex]; 
            } else {
                $post->mini_ads = null; 
            }

            return $post;
        });

        return response()->json([
            'status' => 'success',
            'data' => $posts
        ]);
    }
    

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

        $seed = request()->has('page') ? session()->get('video_seed') : rand(1, 9999);
        if (!request()->has('page')) {
            session()->put('video_seed', $seed);
        }

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
            // ->whereNotIn('id', $watchedVideos)
            ->inRandomOrder($seed) 
            ->paginate(15); 

        
        $videos->getCollection()->transform(function ($video) use ($memberId) {
            $post = $video->post;
            
            if ($post) {
                $isFollowing = Follow::where('follower_id', $memberId)
                    ->where('following_id', $post->member_id)
                    ->exists();
                $post->is_following = $isFollowing;

                $followBoost = FollowBoost::where('member_id', $post->member_id)->first();
                $post->follow_boost_status = ($followBoost && $followBoost->status === 'active') ? 'active' : 'inactive';

                $postBoost = PostBoost::where('post_id', $post->id)->latest()->first();
                if ($postBoost && $postBoost->status === 'active') {
                    $post->post_boost = [
                        'id' => $postBoost->id,
                        'message_link' => $postBoost->message_link,
                        'website_link' => $postBoost->website_link,
                        'status' => 'active'
                    ];
                } else {
                    $post->post_boost = ['id' => null, 'status' => 'inactive'];
                }
            }

            return $video;
        });

        return response()->json([
            'status' => 'success',
            'data' => $videos
        ]);
    }
    
    

// ========= THIS IS OUR POST STORE FUNCTION WITH 18+ CONTENT FILTER START==============
    public function store(Request $request)
    {
        $request->validate([
            'content' => 'nullable|string',
            'visibility' => 'required',
            'media.*' => 'nullable|file|max:102400',
        ]);

        $member = Auth::guard("member")->user();
        if (!$member) return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);

        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        $post = \App\Models\Post::create([
            'member_id' => $member->id,
            'content' => $request->content,
            'visibility' => $request->visibility,
            'status' => 'pending', 
        ]);

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $fileNameBase = time() . '-' . uniqid();

                if (in_array($extension, $imageExtensions)) {
                    $tempPath = $file->storeAs('temp_images', $fileNameBase . '.' . $extension, 'local');
                    \App\Jobs\ProcessImageUpload::dispatch($post->id, storage_path('app/' . $tempPath), $fileNameBase);
                } 
                elseif (in_array($extension, $videoExtensions)) {
                    $tempPath = $file->storeAs('temp_videos', $fileNameBase . '.' . $extension, 'local');
                    \App\Jobs\ProcessVideoSafetyCheck::dispatch($post->id, storage_path('app/' . $tempPath), $extension);
                }
            }
        } else {
            $post->update(['status' => 'active']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Your post is being processed and will be published shortly.',
            'post' => $post->load('media')
        ]);
    }
// ========= THIS IS OUR POST STORE FUNCTION WITH 18+ CONTENT FILTER END ==============


public function trackView(Request $request) {
    $member = Auth::guard('member')->user();
    if (!$member) return response()->json(['message' => 'Unauthorized'], 401);

    $memberId = $member->id;
    $postId = $request->post_id;
    $mediaId = $request->post_media_id; 
    $seconds = (int) $request->seconds;

    if ($postId) {
        $view = PostView::firstOrCreate([
            'post_id' => $postId,
            'member_id' => $memberId
        ], ['viewed_at' => now()]);

        if ($view->wasRecentlyCreated) {
            Post::where('id', $postId)->increment('total_views');
        }
        
        if (!$mediaId) {
            return response()->json(['status' => 'success', 'type' => 'post_view']);
        }
    }

    if ($mediaId) {
       
        $media = \App\Models\Post_media::find($mediaId);
        
        if (!$media) {
            return response()->json(['status' => 'error', 'message' => 'Media not found'], 404);
        }

        $videoView = \App\Models\VideoView::firstOrNew([
            'member_id' => $memberId,
            'post_media_id' => $mediaId
        ]);

        
        if (!$videoView->exists) {
            $videoView->watch_time = 0;
        }

        $updatedTime = (int) $videoView->watch_time + (int) $seconds;

        
        $maxDuration = (int) $media->duration;
        if ($updatedTime > $maxDuration && $maxDuration > 0) {
            $updatedTime = $maxDuration;
        }

        
        $videoView->watch_time = $updatedTime;
        $videoView->save();

        return response()->json([
            'status' => 'success',
            'type' => 'video_watch_time',
            'current_watch_time' => (int) $videoView->watch_time 
        ]);
    }
}






































































































    

























    



//    This is our with out 18 + code start
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'content' => 'nullable|string',
    //         'visibility' => 'required',
    //         'media.*' => 'nullable|file',
    //     ]);

    //     $member = Auth::guard("member")->user();
    //     if (!$member) return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);

    //     $post = Post::create([
    //         'member_id' => $member->id,
    //         'content' => $request->content ?? null,
    //         'boost_status' => $request->boost_status ?? 0,
    //         'visibility' => $request->visibility,
    //         'is_pinned' => $request->is_pinned ?? false,
    //         'scheduled_at' => $request->scheduled_at,
    //     ]);

    //     if ($request->hasFile('media')) {
    //         try {
    //             $keyFileData = config('filesystems.disks.gcs.key_file');
    //             if (!is_array($keyFileData)) $keyFileData = json_decode(file_get_contents(base_path($keyFileData)), true);
                
    //             $storage = new StorageClient(['projectId' => config('filesystems.disks.gcs.project_id'), 'keyFile' => $keyFileData]);
    //             $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

    //             foreach ($request->file('media') as $file) {
    //                 $extension = strtolower($file->getClientOriginalExtension());
    //                 $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    //                 $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];

    //                 if (in_array($extension, $imageExtensions)) {
    //                     $img = Image::make($file->getRealPath());
                        
    //                     $img->resize(1200, null, function ($constraint) {
    //                         $constraint->aspectRatio();
    //                         $constraint->upsize();
    //                     });

    //                     $quality = 85; 
    //                     $encoded = $img->encode('webp', $quality);
                        
    //                     while (strlen($encoded) / 1024 > 500 && $quality > 10) {
    //                         $quality -= 5;
    //                         $encoded = $img->encode('webp', $quality);
    //                     }

    //                     $fileName = 'posts/images/' . time() . '-' . uniqid() . '.webp';
    //                     $bucket->upload($encoded, [
    //                         'name' => $fileName, 
    //                         'metadata' => ['contentType' => 'image/webp']
    //                     ]);

    //                     Post_media::create([
    //                         'post_id' => $post->id,
    //                         'media_type' => 'image',
    //                         'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
    //                     ]);

                  
    //                 } elseif (in_array($extension, $videoExtensions)) {
    //                     $fileName = 'posts/videos/' . time() . '-' . uniqid() . '.' . $extension;
                        
    //                     $bucket->upload(fopen($file->getRealPath(), 'r'), [
    //                         'name' => $fileName,
    //                         'metadata' => ['contentType' => $file->getMimeType()]
    //                     ]);

    //                     Post_media::create([
    //                         'post_id' => $post->id,
    //                         'media_type' => 'video',
    //                         'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
    //                     ]);
    //                 }
    //             }
    //         } catch (\Exception $e) {
    //             \Log::error("Media Upload Error: " . $e->getMessage());
    //         }
    //     }

    //     return response()->json([
    //         'status' => 'success', 
    //         'message' => 'Post created successfully!', 
    //         'post' => $post->load('media')
    //     ]);
    // }

    // This is our with out 18 + code end

    
    


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
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    // public function update(Request $request)
    // {
    //     return "ok";
    //     $member = Auth::guard("member")->user();
    
    //     if (!$member) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Unauthorized user'
    //         ], 401);
    //     }
    
    //     $post = Post::where('id', $id)->where('member_id', $member->id)->first();
    
    //     if (!$post) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Post not found or not authorized'
    //         ], 404);
    //     }
    
    //     $request->validate([
    //         'content' => 'nullable|string',
    //         'visibility' => 'nullable|in:public,private,friends', 
    //         'scheduled_at' => 'nullable|date',
    //         'boost_status' => 'nullable|in:0,1',
    //         'media.*' => 'nullable|file|max:10240', 
    //     ]);
    
    //     $post->update([
    //         'content' => $request->content ?? $post->content,
    //         'visibility' => $request->visibility ?? $post->visibility,
    //         'is_pinned' => $request->is_pinned ?? $post->is_pinned,
    //         'scheduled_at' => $request->scheduled_at ?? $post->scheduled_at,
    //         'boost_status' => $request->boost_status ?? $post->boost_status,
    //     ]);
    
    //     if ($request->remove_old_media && $request->remove_old_media == true) {
    //         foreach ($post->media as $oldMedia) {
    //             $filePath = public_path(str_replace('public/', '', $oldMedia->path));
    //             if (file_exists($filePath)) {
    //                 unlink($filePath);
    //             }
    //             $oldMedia->delete();
    //         }
    //     }
    
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
    //             }
    
                
    //             elseif (in_array($extension, $videoExtensions)) {
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
    //         PostBoost::updateOrCreate(
    //             ['post_id' => $post->id],
    //             [
    //                 'member_id'   => $member->id,
    //                 'age_from'    => $request->age_from,
    //                 'age_to'      => $request->age_to,
    //                 'start_date'  => Carbon::now(),
    //                 'end_date'    => $request->end_date ? Carbon::parse($request->end_date)->format('Y-m-d') : null,
    //                 'gender'      => $request->gender,
    //                 'location'    => $request->location,
    //                 'profession'  => $request->profession,
    //                 'income_range'=> $request->income_range,
    //             ]
    //         );
    //     }
    
    //     $post->load(['boost', 'media']);
    
    //     return response()->json([
    //         'status'  => 'success',
    //         'message' => 'Post updated successfully!',
    //         'post'    => $post,
    //     ]);
    // }



    // public function update(Request $request, $id) 
    // {
    //     $member = Auth::guard("member")->user();
    //     if (!$member) return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);

    //     // পোস্ট খুঁজে বের করা
    //     $post = \App\Models\Post::where('id', $id)->where('member_id', $member->id)->first();
    //     if (!$post) return response()->json(['status' => 'failed', 'message' => 'Post not found'], 404);

    //     // ভ্যালিডেশন
    //     $request->validate([
    //         'content' => 'nullable|string',
    //         'visibility' => 'nullable|in:public,private,friends',
    //         'media.*' => 'nullable|file|max:51200', // 50MB Max
    //     ]);

    //     // ১. পুরনো মিডিয়া ডিলিট লজিক (যদি রিকোয়েস্টে থাকে)
    //     // ইউজার যদি সব মিডিয়া ফেলে দিয়ে নতুন দিতে চায় অথবা স্রেফ ডিলিট করতে চায়
    //     if ($request->remove_old_media == true || $request->remove_old_media == 'true') {
    //         $this->deletePostMediaFromGCS($post);
    //     }

    //     // ২. টেক্সট ডাটা আপডেট
    //     $post->update([
    //         'content'      => $request->has('content') ? $request->content : $post->content,
    //         'visibility'   => $request->has('visibility') ? $request->visibility : $post->visibility,
    //         'is_pinned'    => $request->has('is_pinned') ? $request->is_pinned : $post->is_pinned,
    //         'scheduled_at' => $request->has('scheduled_at') ? $request->scheduled_at : $post->scheduled_at,
    //         'boost_status' => $request->has('boost_status') ? $request->boost_status : $post->boost_status,
    //     ]);

    //     // ৩. নতুন মিডিয়া হ্যান্ডেল করা (ব্যাকগ্রাউন্ড জব)
    //     $hasNewMedia = false;
    //     if ($request->hasFile('media')) {
    //         $hasNewMedia = true;
    //         $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    //         $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];

    //         foreach ($request->file('media') as $file) {
    //             $extension = strtolower($file->getClientOriginalExtension());
    //             $fileNameBase = time() . '-' . uniqid();

    //             if (in_array($extension, $imageExtensions)) {
    //                 $tempPath = $file->storeAs('temp_images', $fileNameBase . '.' . $extension, 'local');
    //                 \App\Jobs\ProcessImageUpload::dispatch($post->id, storage_path('app/' . $tempPath), $fileNameBase);
    //             } 
    //             elseif (in_array($extension, $videoExtensions)) {
    //                 $tempPath = $file->storeAs('temp_videos', $fileNameBase . '.' . $extension, 'local');
    //                 \App\Jobs\ProcessVideoSafetyCheck::dispatch($post->id, storage_path('app/' . $tempPath), $extension);
    //             }
    //         }
    //     }

    //     // ৪. বুস্ট আপডেট (যদি ডাটা থাকে)
    //     if ($request->boost_status == 1) {
    //         $post->boost()->updateOrCreate(
    //             ['post_id' => $post->id],
    //             [
    //                 'member_id'    => $member->id,
    //                 'age_from'     => $request->age_from,
    //                 'age_to'       => $request->age_to,
    //                 'start_date'   => \Carbon\Carbon::now(),
    //                 'end_date'     => $request->end_date ? \Carbon\Carbon::parse($request->end_date)->format('Y-m-d') : null,
    //                 'gender'       => $request->gender,
    //                 'location'     => $request->location,
    //                 'profession'   => $request->profession,
    //                 'income_range' => $request->income_range,
    //             ]
    //         );
    //     }

    //     // ৫. স্ট্যাটাস আপডেট: নতুন মিডিয়া থাকলে পেন্ডিং হবে
    //     if ($hasNewMedia) {
    //         $post->update(['status' => 'pending']);
    //     }

    //     return response()->json([
    //         'status'  => 'success',
    //         'message' => $hasNewMedia ? 'Post updating... Media is being processed.' : 'Post updated successfully!',
    //         'post'    => $post->load(['media', 'boost']),
    //     ]);
    // }

    // /**
    //  * GCS থেকে ফাইল ডিলিট করার প্রাইভেট মেথড
    //  */
    // private function deletePostMediaFromGCS($post)
    // {
    //     $storage = new \Google\Cloud\Storage\StorageClient([
    //         'projectId' => config('filesystems.disks.gcs.project_id'),
    //         'keyFile'   => config('filesystems.disks.gcs.key_file'),
    //     ]);
    //     $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

    //     foreach ($post->media as $media) {
    //         try {
    //             // URL থেকে ফাইলের পাথ বের করা
    //             $urlPath = parse_url($media->path, PHP_URL_PATH); 
    //             $objectName = ltrim(str_replace('/' . config('filesystems.disks.gcs.bucket'), '', $urlPath), '/');

    //             $object = $bucket->object($objectName);
    //             if ($object->exists()) {
    //                 $object->delete();
    //             }
    //         } catch (\Exception $e) {
    //             \Log::error("GCS Delete Error: " . $e->getMessage());
    //         }
    //         $media->delete(); // ডাটাবেস রেকর্ড ডিলিট
    //     }
    // }





    // public function update(Request $request, $id) 
    // {
    //     $member = Auth::guard("member")->user();
    //     if (!$member) return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);

    //     // ১. পোস্টটি খুঁজে বের করা
    //     $post = \App\Models\Post::where('id', $id)->where('member_id', $member->id)->first();
    //     if (!$post) return response()->json(['status' => 'failed', 'message' => 'Post not found'], 404);

    //     // ২. ভ্যালিডেশন
    //     $request->validate([
    //         'content' => 'nullable|string',
    //         'visibility' => 'nullable|in:public,private,friends',
    //         'media.*' => 'nullable|file|max:51200',
    //     ]);

    //     // ৩. পুরনো মিডিয়া সম্পূর্ণ ডিলিট (GCS + DB)
    //     // আপনি যেহেতু চেয়েছেন আগের সব ডিলিট করে নতুন ডাটা সেভ করতে
    //     if ($request->hasFile('media')) {
    //         $this->deletePostMediaFromGCS($post);
    //     }

    //     // ৪. টেক্সট ডাটা আপডেট
    //     // রিকোয়েস্টে যা আছে তা দিয়ে আপডেট হবে, না থাকলে আগেরটাই থাকবে
    //     $post->update([
    //         'content'      => $request->content ?? $post->content,
    //         'visibility'   => $request->visibility ?? $post->visibility,
    //         'is_pinned'    => $request->is_pinned ?? $post->is_pinned,
    //         'scheduled_at' => $request->scheduled_at ?? $post->scheduled_at,
    //         'boost_status' => $request->boost_status ?? $post->boost_status,
    //         'status'       => $request->hasFile('media') ? 'pending' : $post->status,
    //     ]);

    //     // ৫. নতুন মিডিয়া প্রসেসিং (Jobs)
    //     if ($request->hasFile('media')) {
    //         $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    //         $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];

    //         foreach ($request->file('media') as $file) {
    //             $extension = strtolower($file->getClientOriginalExtension());
    //             $fileNameBase = time() . '-' . uniqid();

    //             if (in_array($extension, $imageExtensions)) {
    //                 $tempPath = $file->storeAs('temp_images', $fileNameBase . '.' . $extension, 'local');
    //                 \App\Jobs\ProcessImageUpload::dispatch($post->id, storage_path('app/' . $tempPath), $fileNameBase);
    //             } 
    //             elseif (in_array($extension, $videoExtensions)) {
    //                 $tempPath = $file->storeAs('temp_videos', $fileNameBase . '.' . $extension, 'local');
    //                 \App\Jobs\ProcessVideoSafetyCheck::dispatch($post->id, storage_path('app/' . $tempPath), $extension);
    //             }
    //         }
    //     }

    //     // ৬. বুস্ট ডাটা আপডেট
    //     if ($request->boost_status == 1) {
    //         $post->boost()->updateOrCreate(
    //             ['post_id' => $post->id],
    //             [
    //                 'member_id'    => $member->id,
    //                 'age_from'     => $request->age_from,
    //                 'age_to'       => $request->age_to,
    //                 'start_date'   => \Carbon\Carbon::now(),
    //                 'end_date'     => $request->end_date ? \Carbon\Carbon::parse($request->end_date)->format('Y-m-d') : null,
    //                 'gender'       => $request->gender,
    //                 'location'     => $request->location,
    //                 'profession'   => $request->profession,
    //                 'income_range' => $request->income_range,
    //             ]
    //         );
    //     }

    //     return response()->json([
    //         'status'  => 'success',
    //         'message' => $request->hasFile('media') ? 'Post updating with new media...' : 'Post updated successfully!',
    //         'post'    => $post->load(['media', 'boost']),
    //     ]);
    // }

    // /**
    //  * GCS এবং ডাটাবেস থেকে মিডিয়া ডিলিট করার মেথড
    //  */
    // private function deletePostMediaFromGCS($post)
    // {
    //     $storage = new \Google\Cloud\Storage\StorageClient([
    //         'projectId' => config('filesystems.disks.gcs.project_id'),
    //         'keyFile'   => config('filesystems.disks.gcs.key_file'),
    //     ]);
    //     $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

    //     foreach ($post->media as $media) {
    //         try {
    //             // URL থেকে অবজেক্ট নেম বের করা
    //             $pathParts = explode(config('filesystems.disks.gcs.bucket') . '/', $media->path);
    //             $objectName = end($pathParts);

    //             $object = $bucket->object($objectName);
    //             if ($object->exists()) {
    //                 $object->delete();
    //             }
    //         } catch (\Exception $e) {
    //             \Log::error("GCS File Delete Failed: " . $e->getMessage());
    //         }
    //         // ডাটাবেস থেকে রেকর্ড ডিলিট
    //         $media->delete();
    //     }
    // }



    public function update(Request $request, $id) 
    {
        $member = Auth::guard("member")->user();
        if (!$member) return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);

        $post = \App\Models\Post::where('id', $id)->where('member_id', $member->id)->first();
        if (!$post) return response()->json(['status' => 'failed', 'message' => 'Post not found'], 404);

        $request->validate([
            'content' => 'nullable|string',
            'visibility' => 'nullable|in:public,private,friends',
            'media.*' => 'nullable|file|max:102400', // ১০০ এমবি পর্যন্ত
            'deleted_media_ids' => 'nullable|array', // যে আইডিগুলো ইউজার ডিলিট করতে চায়
            'deleted_media_ids.*' => 'exists:post_media,id'
        ]);

        // ১. নির্দিষ্ট মিডিয়া ডিলিট করা (যদি ইউজার কোনোটা রিমুভ করে)
        if ($request->has('deleted_media_ids')) {
            $this->deleteSpecificMediaFromGCS($request->deleted_media_ids);
        }

        // ২. পোস্ট আপডেট
        $post->update([
            'content'    => $request->has('content') ? $request->content : $post->content,
            'visibility' => $request->visibility ?? $post->visibility,
            'status'     => $request->hasFile('media') ? 'pending' : $post->status,
        ]);

        // ৩. নতুন মিডিয়া প্রসেসিং (পুরনোগুলো ডিলিট না করে নতুনগুলো যোগ হবে)
        if ($request->hasFile('media')) {
            $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];

            foreach ($request->file('media') as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $fileNameBase = time() . '-' . uniqid();

                if (in_array($extension, $imageExtensions)) {
                    $tempPath = $file->storeAs('temp_images', $fileNameBase . '.' . $extension, 'local');
                    \App\Jobs\ProcessImageUpload::dispatch($post->id, storage_path('app/' . $tempPath), $fileNameBase);
                } 
                elseif (in_array($extension, $videoExtensions)) {
                    $tempPath = $file->storeAs('temp_videos', $fileNameBase . '.' . $extension, 'local');
                    \App\Jobs\ProcessVideoSafetyCheck::dispatch($post->id, storage_path('app/' . $tempPath), $extension);
                }
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Post update initiated...',
            'post'    => $post->load(['media', 'boost']),
        ]);
    }

    /**
     * শুধুমাত্র নির্দিষ্ট আইডিগুলো ডিলিট করার মেথড
     */
    private function deleteSpecificMediaFromGCS($mediaIds)
    {
        $storage = new \Google\Cloud\Storage\StorageClient([
            'projectId' => config('filesystems.disks.gcs.project_id'),
            'keyFile'   => config('filesystems.disks.gcs.key_file'),
        ]);
        $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

        $allMedia = \App\Models\Post_media::whereIn('id', $mediaIds)->get();

        foreach ($allMedia as $media) {
            try {
                $pathParts = explode(config('filesystems.disks.gcs.bucket') . '/', $media->path);
                $objectName = end($pathParts);
                $object = $bucket->object($objectName);
                
                if ($object->exists()) {
                    $object->delete();
                }
            } catch (\Exception $e) {
                \Log::error("GCS Delete Error: " . $e->getMessage());
            }
            $media->delete();
        }
    }


   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   

    public function destroy($id)
    {
        $member = Auth::guard("member")->user();
        if (!$member) return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);

        // ১. পোস্টটি খুঁজে বের করা (নিশ্চিত করা যে এটি এই ইউজারেরই পোস্ট)
        $post = \App\Models\Post::where('id', $id)->where('member_id', $member->id)->first();

        if (!$post) {
            return response()->json(['status' => 'failed', 'message' => 'Post not found or unauthorized'], 404);
        }

        try {
            \DB::beginTransaction();

            // ২. GCS থেকে সব মিডিয়া ফাইল ডিলিট করা
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'   => config('filesystems.disks.gcs.key_file'),
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            foreach ($post->media as $media) {
                try {
                    // URL থেকে অবজেক্ট নেম বের করা
                    $pathParts = explode(config('filesystems.disks.gcs.bucket') . '/', $media->path);
                    $objectName = end($pathParts);

                    $object = $bucket->object($objectName);
                    if ($object->exists()) {
                        $object->delete();
                    }
                } catch (\Exception $e) {
                    \Log::error("GCS Delete Error in Destroy: " . $e->getMessage());
                    // GCS ফাইল ডিলিট না হলেও আমরা ডাটাবেস ডিলিট কন্টিনিউ করতে পারি
                }
            }

            // ৩. রিলেটেড ডাটা ডিলিট (Like, Comment, Media, Boost)
            // দ্রষ্টব্য: আপনার মডেলে যদি Cascade Delete সেট করা না থাকে তবে ম্যানুয়ালি ডিলিট করতে হবে।
            $post->media()->delete();      // PostMedia টেবিল থেকে
            $post->likes()->delete();      // Like টেবিল থেকে
            $post->comments()->delete();   // Comment টেবিল থেকে
            $post->boost()->delete();      // Boost টেবিল থেকে (যদি থাকে)

            // ৪. মূল পোস্ট ডিলিট করা
            $post->delete();

            \DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Post and all associated data deleted successfully!',
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Post Destroy Error: " . $e->getMessage());
            return response()->json([
                'status' => 'failed',
                'message' => 'Something went wrong while deleting the post.',
                'error' => $e->getMessage()
            ], 500);
        }
    }






}
