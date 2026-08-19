<footer class="giya-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <div>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="{{ asset('images/logo/giya-logo.svg') }}" alt="GIYA" width="28" height="28">
                    <span class="footer-logo-name">Giya</span>
                </div>
                <p class="footer-desc">
                    Your localized travel companion for pilgrimage and religious tourism
                    in Metro Cebu, Philippines.
                </p>
            </div>

            <div>
                <div class="footer-col-title">Explore</div>
                <a href="{{ route('map') }}"          class="footer-link">Find Churches</a>
                @auth
                    <a href="{{ route('plan.create') }}"  class="footer-link">Plan Route</a>
                    <a href="{{ route('plan.visita') }}"  class="footer-link">Visita Iglesia</a>
                    <a href="{{ route('chatbot') }}"      class="footer-link">AI Chatbot</a>
                @else
                    <a href="{{ route('login') }}"    class="footer-link">Sign In to Plan</a>
                    <a href="{{ route('register') }}" class="footer-link">Create an Account</a>
                @endauth
            </div>

            <div>
                <div class="footer-col-title">Account</div>
                @auth
                    <a href="{{ route('profile') }}"    class="footer-link">My Profile</a>
                    <a href="{{ route('plan.index') }}" class="footer-link">My Itineraries</a>
                    <a href="{{ route('home') }}"       class="footer-link">Dashboard</a>
                @else
                    <a href="{{ route('login') }}"    class="footer-link">Sign In</a>
                    <a href="{{ route('register') }}" class="footer-link">Register</a>
                @endauth
            </div>
        </div>

        <div class="footer-bottom">
            <span class="footer-copy">&copy; {{ date('Y') }} Giya · Metro Cebu Religious Tourism</span>
            <span class="d-flex align-items-center gap-2" style="color:rgba(255,255,255,0.3);font-size: 0.6875rem">
                <span style="width:6px;height:6px;border-radius:50%;background:var(--gold)"></span>
                Made with faith in Cebu
            </span>
        </div>
    </div>
</footer>
