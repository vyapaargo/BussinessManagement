<header class="site">
  <nav class="wrap nav">
    <div class="logo">
      <a href="{{ route('welcome') }}" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
        <img src="{{ asset('images/logo.png') }}" alt="Vyapaargo Logo" style="height:36px; width:auto; max-width:44px; object-fit:contain; display:block;">
        <span style="font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:20px; color:var(--ink); letter-spacing:-0.02em;">Vyapaargo</span>
      </a>
    </div>
    <div class="nav-links">
      <a href="{{ route('public.features') }}">Features</a>
      <a href="{{ route('public.restaurant') }}">Restaurant</a>
      <a href="{{ route('public.payments') }}">Payments</a>
      <a href="{{ route('public.pricing') }}">Pricing</a>
    </div>
    <div class="nav-cta">
      @auth
          <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">See Dashboard</a>
      @else
          <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Log in</a>
          <a class="btn btn-gold btn-sm" href="{{ route('public.pricing') }}">Get Started</a>
      @endauth
    </div>
    
    <!-- Mobile hamburger toggle -->
    <button class="menu-toggle" id="mob-toggle" aria-label="Toggle Navigation">
      <span></span>
      <span></span>
      <span></span>
    </button>
  </nav>
</header>

<!-- Mobile Menu Drawer Container -->
<div class="mobile-menu" id="mob-drawer">
  <a href="{{ route('public.features') }}">Features</a>
  <a href="{{ route('public.restaurant') }}">Restaurant</a>
  <a href="{{ route('public.payments') }}">Payments</a>
  <a href="{{ route('public.pricing') }}">Pricing</a>
  <a href="javascript:void(0)" onclick="triggerAddToHomeScreen(); if (document.getElementById('mob-drawer')) document.getElementById('mob-drawer').classList.remove('open'); if (document.getElementById('mob-backdrop')) document.getElementById('mob-backdrop').classList.remove('open'); document.body.style.overflow='';" style="display:flex; align-items:center; gap:10px; color:var(--ink); font-weight:600; font-size:16px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--gold-deep); flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
    <span>Add to Home Screen</span>
  </a>
  <div class="mob-cta">
    @auth
        <a class="btn btn-ghost" href="{{ route('dashboard') }}">See Dashboard</a>
    @else
        <a class="btn btn-ghost" href="{{ route('login') }}">Log in</a>
        <a class="btn btn-gold" href="{{ route('public.pricing') }}">Get Started</a>
    @endauth
  </div>
</div>

<div class="mobile-menu-backdrop" id="mob-backdrop"></div>