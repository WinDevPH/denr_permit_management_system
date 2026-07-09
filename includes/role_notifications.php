<?php
/**
 * Shared modern toast notifications — include once per page (before role_notifications.js).
 */
?>
<link rel="stylesheet" href="../../../assets/css/role_notifications.css">

<div class="modern-notification success" id="successNotification" role="alert" aria-live="polite">
    <div class="notification-content">
        <div class="notification-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="notification-text">
            <h6 class="notification-title">Success</h6>
            <p class="notification-message"></p>
        </div>
    </div>
    <button type="button" class="notification-close" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="notification-progress"></div>
</div>

<div class="modern-notification error" id="errorNotification" role="alert" aria-live="assertive">
    <div class="notification-content">
        <div class="notification-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="notification-text">
            <h6 class="notification-title">Error</h6>
            <p class="notification-message"></p>
        </div>
    </div>
    <button type="button" class="notification-close" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="notification-progress"></div>
</div>

<script src="../../../assets/js/role_notifications.js"></script>
