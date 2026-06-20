<?php include 'includes/header.php'; ?>
<style>
/* ============================================
   MODERN SPORTS THEME - DARK & REALISTIC
   ============================================ */

:root {
    --primary: #0b132b;
    --secondary: #1c2541;
    --accent: #ffb703;
    --accent-dark: #fb8500;
    --text-light: #f8fafc;
    --text-muted: #94a3b8;
    --glass-bg: rgba(255, 255, 255, 0.03);
    --glass-border: rgba(255, 255, 255, 0.08);
}

.saas-hero {
    min-height: 100vh;
    background:
        radial-gradient(ellipse at 20% 0%, rgba(255, 183, 3, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 100%, rgba(251, 133, 0, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(28, 37, 65, 0.8) 0%, transparent 100%),
        linear-gradient(180deg, #0b132b 0%, #1c2541 50%, #0f172a 100%);
    position: relative;
    display: flex;
    align-items: center;
    overflow: hidden;
}

/* Animated background particles */
.saas-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image:
        radial-gradient(circle at 20% 30%, rgba(255, 183, 3, 0.03) 0%, transparent 25%),
        radial-gradient(circle at 80% 70%, rgba(251, 133, 0, 0.03) 0%, transparent 25%);
    animation: pulse 8s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Grid pattern overlay */
.saas-hero::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
    background-size: 60px 60px;
    pointer-events: none;
}

.saas-hero .container {
    position: relative;
    z-index: 2;
}

/* Floating sports icons background */
.floating-icons {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: 1;
}

.floating-icon {
    position: absolute;
    font-size: 2rem;
    opacity: 0.06;
    animation: float 20s linear infinite;
}

.floating-icon:nth-child(1) { top: 15%; left: 10%; animation-delay: 0s; }
.floating-icon:nth-child(2) { top: 25%; right: 15%; animation-delay: -4s; }
.floating-icon:nth-child(3) { bottom: 20%; left: 20%; animation-delay: -8s; }
.floating-icon:nth-child(4) { top: 40%; right: 25%; animation-delay: -12s; }
.floating-icon:nth-child(5) { bottom: 30%; right: 10%; animation-delay: -16s; }

@keyframes float {
    0% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-30px) rotate(10deg); }
    100% { transform: translateY(0) rotate(0deg); }
}

.saas-hero .row {
    min-height: 100vh;
    align-items: center;
}

/* Left Column */
.saas-hero-left {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* Badge */
.saas-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(255, 183, 3, 0.1);
    border: 1px solid rgba(255, 183, 3, 0.2);
    border-radius: 50px;
    margin-bottom: 24px;
    width: fit-content;
    animation: fadeInDown 0.6s ease-out;
}

.saas-badge-dot {
    width: 8px;
    height: 8px;
    background: #22c55e;
    border-radius: 50%;
    animation: blink 2s ease-in-out infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.saas-badge span {
    font-size: 0.8rem;
    font-weight: 500;
    color: #ffb703;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.saas-heading {
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
    color: #ffffff;
    margin-bottom: 24px;
    animation: fadeInUp 0.6s ease-out 0.1s both;
}

.saas-heading .brand {
    font-family: 'Rockwell', 'Rockwell Extra Bold', serif;
    font-weight: 900;
    text-transform: uppercase;
    background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: 0.06em;
}

.saas-heading .highlight {
    color: #ffb703;
    position: relative;
    font-size: 0.65em;
    font-weight: 500;
    letter-spacing: 0.02em;
    display: block;
    margin-top: 8px;
}

.saas-desc {
    font-size: 1.1rem;
    line-height: 1.8;
    color: #94a3b8;
    max-width: 520px;
    margin-bottom: 36px;
    animation: fadeInUp 0.6s ease-out 0.2s both;
}

/* Buttons */
.saas-btns {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    animation: fadeInUp 0.6s ease-out 0.3s both;
}

.saas-btn-reg {
    padding: 16px 40px;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 12px;
    background: linear-gradient(135deg, #ffb703 0%, #fb8500 100%);
    border: none;
    color: #0b132b;
    box-shadow: 0 8px 32px rgba(255, 183, 3, 0.35);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.saas-btn-reg::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s ease;
}

.saas-btn-reg:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 40px rgba(255, 183, 3, 0.45);
    color: #0b132b;
}

.saas-btn-reg:hover::before {
    left: 100%;
}

.saas-btn-login {
    padding: 16px 40px;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 12px;
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.15);
    color: #ffffff;
    transition: all 0.3s ease;
}

.saas-btn-login:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.3);
    color: #ffffff;
    transform: translateY(-3px);
}

/* Right Column - Feature Cards */
.saas-hero-right {
    display: flex;
    align-items: center;
    justify-content: center;
}

.saas-features-card {
    width: 100%;
    max-width: 540px;
    padding: 32px;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    animation: fadeInRight 0.8s ease-out 0.4s both;
}

@keyframes fadeInRight {
    from { opacity: 0; transform: translateX(30px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Features Grid */
.saas-features-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

/* Individual Feature Card */
.saas-feature {
    padding: 20px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    transition: all 0.3s ease;
}

.saas-feature:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 183, 3, 0.2);
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
}

.saas-feature-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    background: rgba(255, 183, 3, 0.1);
    transition: all 0.3s ease;
}

.saas-feature:hover .saas-feature-icon {
    background: rgba(255, 183, 3, 0.2);
    transform: scale(1.1);
}

.saas-feature-icon i {
    font-size: 1.25rem;
    color: #ffb703;
}

.saas-feature h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #ffffff;
    margin-bottom: 6px;
}

.saas-feature p {
    font-size: 0.8rem;
    color: #94a3b8;
    line-height: 1.5;
    margin-bottom: 0;
}

/* Responsive */
@media (max-width: 991px) {
    .saas-hero {
        min-height: auto;
        padding: 100px 0 60px;
    }

    .saas-hero .row {
        min-height: auto;
    }

    .saas-hero-left {
        text-align: center;
        align-items: center;
        margin-bottom: 50px;
    }

    .saas-badge {
        margin-left: auto;
        margin-right: auto;
    }

    .saas-desc {
        margin-left: auto;
        margin-right: auto;
    }

    .saas-btns {
        justify-content: center;
    }

    .saas-stats {
        justify-content: center;
    }

    .saas-hero-right {
        margin-bottom: 30px;
    }

    .saas-features-card {
        max-width: 480px;
        margin: 0 auto;
    }
}

@media (max-width: 576px) {
    .saas-heading {
        font-size: 2.2rem;
    }

    .saas-desc {
        font-size: 0.95rem;
    }

    .saas-btns {
        flex-direction: column;
        width: 100%;
    }

    .saas-btn-reg,
    .saas-btn-login {
        width: 100%;
        text-align: center;
    }

    .saas-features-card {
        padding: 20px;
    }

    .saas-features-grid {
        grid-template-columns: 1fr;
    }

    .saas-stats {
        flex-direction: column;
        gap: 20px;
        align-items: center;
    }

    .saas-stat {
        text-align: center;
    }
}
</style>

<!-- ============================================
   MODERN SPORTS HERO SECTION
   ============================================ -->
<section class="saas-hero">
    <!-- Floating sports icons -->
    <div class="floating-icons">
        <div class="floating-icon"><i class="bi bi-trophy-fill"></i></div>
        <div class="floating-icon"><i class="bi bi-people-fill"></i></div>
        <div class="floating-icon"><i class="bi bi-calendar-event"></i></div>
        <div class="floating-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="floating-icon"><i class="bi bi-shield-check"></i></div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-6">
                <div class="saas-hero-left">
                    <!-- Badge -->
                    <div class="saas-badge">
                        <div class="saas-badge-dot"></div>
                        <span>Sports Event Platform</span>
                    </div>

                    <h1 class="saas-heading">
                        <span class="brand">SportsZone</span><br>
                        <span class="highlight">Sports Event Management System</span>
                    </h1>
                    <p class="saas-desc">
                        The ultimate sports event management platform to organize tournaments,
                        manage registrations, track participants, and celebrate victories —
                        all in one powerful system designed for institutions and sports communities.
                    </p>
                    <div class="saas-btns">
                        <a href="register.php" class="saas-btn-reg">
                            <i class="bi bi-person-plus me-2"></i>Register Now
                        </a>
                        <a href="login.php" class="saas-btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-6">
                <div class="saas-hero-right">
                    <div class="saas-features-card">
                        <div class="saas-features-grid">
                            <!-- Feature 1 -->
                            <div class="saas-feature">
                                <div class="saas-feature-icon">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                                <h4>Event Management</h4>
                                <p>Create and manage sports events with ease.</p>
                            </div>

                            <!-- Feature 2 -->
                            <div class="saas-feature">
                                <div class="saas-feature-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                                <h4>Registration</h4>
                                <p>Simple student registration process.</p>
                            </div>

                            <!-- Feature 3 -->
                            <div class="saas-feature">
                                <div class="saas-feature-icon">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <h4>Live Tracking</h4>
                                <p>Real-time event updates and stats.</p>
                            </div>

                            <!-- Feature 4 -->
                            <div class="saas-feature">
                                <div class="saas-feature-icon">
                                    <i class="bi bi-shield-lock"></i>
                                </div>
                                <h4>Secure Access</h4>
                                <p>Safe authentication for all users.</p>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>