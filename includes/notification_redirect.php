<?php
/**
 * Target page for clicking an in-app notification (relative URL from modules/{role}/.../).
 */
if (!function_exists('denr_notification_redirect_for_role')) {
    function denr_notification_redirect_for_role(string $role, string $message): string
    {
        $m = strtolower($message);

        // Messages inbox
        if (strpos($m, 'new message') !== false) {
            return '../messages/messages.php';
        }

        // User / account management (admin)
        if ($role === 'admin' && (strpos($m, 'landowner registration') !== false || strpos($m, 'new user') !== false)) {
            return '../users/users.php';
        }

        // Documents
        if (strpos($m, 'document') !== false) {
            if ($role === 'landowner') {
                return '../documents/documents.php';
            }
            return '../plantations/plantations.php';
        }

        // Plantation / field visit schedules
        if (strpos($m, 'verification schedule') !== false
            || strpos($m, 'verification visit') !== false
            || strpos($m, 'visit plantation') !== false
            || strpos($m, 'pending verification schedule') !== false
            || strpos($m, 'scheduled for your plantation') !== false) {
            if ($role === 'verifier') {
                return '../calendar/calendar.php';
            }
            if ($role === 'landowner') {
                return '../plantations/plantations.php';
            }
            return '../calendar/calendar.php';
        }

        // Permit requests, issuance, approval, rejection
        if (strpos($m, 'permit') !== false) {
            return '../permits/permits.php';
        }

        // Plantation applications and status updates
        if (strpos($m, 'plantation') !== false) {
            return '../plantations/plantations.php';
        }

        return '../dashboard/dashboard.php';
    }
}
