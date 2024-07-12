@extends('layouts/blankLayout')

@section('title', 'Terms and Conditions')

@section('page-style')
  {{-- Page Css files --}}
  <link rel="stylesheet" href="{{ asset(mix('assets/vendor/css/pages/page-auth.css')) }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-basic px-4">
  <div class="authentication-inner py-4">
    <!-- Logo -->
    <div class="app-brand mb-4">
      <a href="{{ url('/') }}" class="app-brand-link gap-2">
        <span class="app-brand-logo demo">@include('_partials.macros', ["height" => 20, "withbg" => 'fill: #fff;'])</span>
      </a>
    </div>
    <!-- /Logo -->
    <div class="card">
      <div class="card-body">
        <h2>Terms and Conditions</h2>
        
        <h3>1. Introduction</h3>
        <p>Welcome to <strong>{{ $configData['templateName'] }}</strong>. These terms and conditions outline the rules and regulations for the use of our website.</p>
        
        <h3>2. Acceptance of Terms</h3>
        <p>By accessing this website, we assume you accept these terms and conditions. Do not continue to use <strong>{{ $configData['templateName'] }}</strong> if you do not agree to all of the terms and conditions stated on this page.</p>
        
        <h3>3. Cookies</h3>
        <p>We employ the use of cookies. By accessing <strong>{{ $configData['templateName'] }}</strong>, you agreed to use cookies in agreement with our privacy policy.</p>
        
        <h3>4. License</h3>
        <p>Unless otherwise stated, <strong>{{ $configData['creatorName'] }}</strong> and/or its licensors own the intellectual property rights for all material on <strong>{{ $configData['templateName'] }}</strong>. All intellectual property rights are reserved. You may access this from <strong>{{ $configData['templateName'] }}</strong> for your own personal use subjected to restrictions set in these terms and conditions.</p>
        
        <h3>5. User Comments</h3>
        <p>Certain parts of this website offer the opportunity for users to post and exchange opinions and information in certain areas of the website. <strong>{{ $configData['creatorName'] }}</strong> does not filter, edit, publish or review Comments prior to their presence on the website.</p>
        
        <h3>6. Content Liability</h3>
        <p>We shall not be hold responsible for any content that appears on your website. You agree to protect and defend us against all claims that is rising on your website.</p>
        
        <h3>7. Your Privacy</h3>
        <p>Please read our <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>
        
        <h3>8. Reservation of Rights</h3>
        <p>We reserve the right to request that you remove all links or any particular link to our website. You approve to immediately remove all links to our website upon request.</p>
        
        <h3>9. Removal of links from our website</h3>
        <p>If you find any link on our website that is offensive for any reason, you are free to contact and inform us any moment. We will consider requests to remove links but we are not obligated to or so or to respond to you directly.</p>
        
        <h3>10. Disclaimer</h3>
        <p>To the maximum extent permitted by applicable law, we exclude all representations, warranties and conditions relating to our website and the use of this website.</p>
        
        <h3>11. Changes to Terms</h3>
        <p>We may update our Terms and Conditions from time to time. We will notify you of any changes by posting the new Terms and Conditions on this page.</p>
        
        <h3>12. Contact Us</h3>
        <p>If you have any questions about these Terms, please contact us at <a href="{{ $configData['support'] }}">Support</a>.</p>

        <h3>Follow Us</h3>
        <ul>
          @if (!empty($configData['facebookUrl']))
            <li><a href="{{ $configData['facebookUrl'] }}">Facebook</a></li>
          @endif
          @if (!empty($configData['twitterUrl']))
            <li><a href="{{ $configData['twitterUrl'] }}">Twitter</a></li>
          @endif
          @if (!empty($configData['githubUrl']))
            <li><a href="{{ $configData['githubUrl'] }}">GitHub</a></li>
          @endif
          @if (!empty($configData['dribbbleUrl']))
            <li><a href="{{ $configData['dribbbleUrl'] }}">Dribbble</a></li>
          @endif
          @if (!empty($configData['instagramUrl']))
            <li><a href="{{ $configData['instagramUrl'] }}">Instagram</a></li>
          @endif
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
