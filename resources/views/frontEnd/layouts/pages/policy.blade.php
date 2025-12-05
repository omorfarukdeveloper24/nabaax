@extends('frontEnd.layouts.master')
@section('title', 'Privacy Policy')
@section('content')

<section class="privacy-policy">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center mb-5 mt-5">Privacy Policy</h1>
                
               <p style="
                    margin: 0;
                    color: #000;
                    padding: 0;
                    font-size: 18px;
                    font-family: system-ui;
                    line-height: 37px;
                    padding: 0px 173px;
                ">
                  Welcome to Nabaax — a social earning platform where users can connect, share posts, and earn rewards through likes, comments, and referrals. Your privacy is important to us, and we are committed to protecting your personal information.

                    1. Information We Collect
                    
                    We collect the following types of information to provide and improve our services:
                    
                    Personal Information: Name, email address, date of birth, and verification documents (e.g., National ID, Birth Certificate, Driving License, or Passport).
                    
                    Usage Data: Information about your activity on the app, such as posts, likes, comments, and referral activities.
                    
                    Device Information: Device type, operating system, and general log data for app performance and security.
                    
                    2. How We Use Your Information
                    
                    We use your information to:
                    
                    Verify user identity and prevent fake or duplicate accounts.
                    
                    Enable earnings from posts, likes, comments, and referrals.
                    
                    Improve user experience and app performance.
                    
                    Communicate with you about updates, features, or support.
                    
                    3. Data Security
                    
                    We use advanced security measures to protect your personal data from unauthorized access, alteration, or disclosure. Your verification documents are securely stored and never shared with third parties.
                    
                    4. Data Sharing
                    
                    We do not sell, rent, or share any user information with third parties. Your data is only used within the app to provide our core services and ensure user authenticity.
                    
                    5. User Rights
                    
                    You can:
                    
                    View and update your profile information.
                    
                    Request deletion of your account and associated data.
                    
                    Contact us anytime regarding data concerns or policy questions.
                    
                    6. Children’s Privacy
                    
                    Our services are not intended for children under the age of 13. We do not knowingly collect personal information from children.
                    
                    7. Changes to This Policy
                    
                    We may update this Privacy Policy from time to time. All changes will be reflected on this page with a revised effective date.

               </p>
            </div>
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>
    .privacy-policy {
        padding: 60px 0;
        line-height: 1.6;
    }
    
    .policy-section {
        margin-bottom: 30px;
    }
    
    .policy-section h2 {
        font-size: 1.5rem;
        margin-bottom: 15px;
        color: #2c3e50;
    }
    
    .policy-section ul {
        padding-left: 20px;
    }
    
    .policy-section li {
        margin-bottom: 8px;
    }
</style>
@endpush