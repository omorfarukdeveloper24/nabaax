<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Member extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'phone', 'password', 'referrer_id', 'referrer_code', 
        'partner_code', 'only_reffer', 'balance', 'phoneverify', 'approved', 
        'gender', 'blood', 'religion', 'monthlyincome', 'profession', 
        'nationality', 'married', 'division', 'district', 'upazila', 
        'verified', 'city', 'country','partner','submit', 'status',

        // --- নতুন যোগ করা কলামগুলো নিচে দেওয়া হলো ---
        'monetization', 
        'total_earned', 
        'monetization_activated_at', 
        'initial_views', 
        'initial_watch_time', 
        'last_paid_views', 
        'last_paid_watch_time'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * UPDATED: $casts প্রপার্টি যুক্ত করা হয়েছে।
     * এটি ব্যালেন্স এবং তারিখগুলোকে সঠিক ফরম্যাটে (decimal/datetime) রূপান্তর করবে।
     */
    
    protected $casts = [
        'monetization' => 'boolean',
        'monetization_activated_at' => 'datetime',
        'balance' => 'decimal:4',     
        'total_earned' => 'decimal:4',
        'approved' => 'boolean',
        'status' => 'boolean',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey(); 
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    

    public function referrer()
    {
        return $this->belongsTo(Member::class, 'referrer_id');
    }

    // Relation: jhade sa refer koreache
    public function referrals()
    {
        return $this->hasMany(Member::class, 'referrer_id');
    }

    public function allReferrals()
    {
        return $this->referrals()
                    ->with('allReferrals')
                    ->select('id', 'name', 'username', 'referrer_id', 'balance', 'approved');
    }
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    public function media()
    {
        return $this->hasMany(Post_media::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function shares()
    {
        return $this->hasMany(Share::class);
    }
    
    
    public function sentRequests()
    {
        return $this->hasMany(Friendship::class, 'sender_id');
    }
    
    public function receivedRequests()
    {
        return $this->hasMany(Friendship::class, 'receiver_id');
    }
    
    public function friends()
    {
        return $this->belongsToMany(Member::class, 'friendships', 'sender_id', 'receiver_id')
                    ->wherePivot('status', 'accepted')
                    ->withTimestamps();
    }
    
    public function followers()
    {
        return $this->hasMany(Follow::class, 'following_id');
    }
    
    public function followings()
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }
    
    public function followBoosts()
    {
        return $this->hasMany(FollowBoost::class, 'member_id');
    }
    
   



}
