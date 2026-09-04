<footer class="giya-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <div>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="{{ asset('images/logo/giya-logo.svg') }}" alt="GIYA" width="28" height="28">
                    <span class="footer-logo-name">Giya</span>
                </div>
                <p class="footer-desc">
                    {{ __('giya.misc.footer_lead') }}
                </p>
            </div>

            <div>
                <div class="footer-col-title">{{ __('giya.footer.explore') }}</div>
                <a href="{{ route('map') }}"          class="footer-link">{{ __('giya.footer.find_churches') }}</a>
                @auth
                    <a href="{{ route('plan.create') }}"  class="footer-link">{{ __('giya.footer.plan_route') }}</a>
                    <a href="{{ route('plan.visita') }}"  class="footer-link">{{ __('giya.footer.visita') }}</a>
                    <a href="{{ route('chatbot') }}"      class="footer-link">{{ __('giya.footer.chatbot') }}</a>
                @else
                    <a href="{{ route('login') }}"    class="footer-link">{{ __('giya.footer.sign_in_plan') }}</a>
                    <a href="{{ route('register') }}" class="footer-link">{{ __('giya.footer.create_account') }}</a>
                @endauth
            </div>

            <div>
                <div class="footer-col-title">{{ __('giya.footer.account') }}</div>
                @auth
                    <a href="{{ route('profile') }}"    class="footer-link">{{ __('giya.footer.my_profile') }}</a>
                    <a href="{{ route('plan.index') }}" class="footer-link">{{ __('giya.footer.my_itineraries') }}</a>
                    <a href="{{ route('home') }}"       class="footer-link">{{ __('giya.footer.dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}"    class="footer-link">{{ __('giya.nav.sign_in') }}</a>
                    <a href="{{ route('register') }}" class="footer-link">{{ __('giya.footer.register') }}</a>
                @endauth
            </div>
        </div>

        <div class="footer-bottom">
            <span class="footer-copy">&copy; {{ date('Y') }} Giya · {{ __('giya.hub.copyright') }}</span>
            <span class="d-flex align-items-center gap-2" style="color:rgba(255,255,255,0.3);font-size: 0.6875rem">
                <span style="width:6px;height:6px;border-radius:50%;background:var(--gold)"></span>
                {{ __('giya.misc.made_with') }}
            </span>
        </div>
    </div>
</footer>
