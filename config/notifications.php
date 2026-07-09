<?php

/**
 * Central in-app notification helpers for all DENR roles.
 */

if (!function_exists('denr_notifications_has_scheduled_column')) {
    function denr_notifications_has_scheduled_column(PDO $db): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $cached = (bool) $db->query("SHOW COLUMNS FROM notifications LIKE 'scheduled_time'")->fetch();
        } catch (Throwable $e) {
            $cached = false;
        }
        return $cached;
    }
}

if (!function_exists('denr_get_active_user_ids_by_role')) {
    function denr_get_active_user_ids_by_role(PDO $db, string $role): array
    {
        try {
            $q = $db->prepare(
                "SELECT user_id FROM users WHERE role = ? AND (status IS NULL OR status = 'active')"
            );
            $q->execute([$role]);
            $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN, 0) ?: []);
            return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
        } catch (Throwable $e) {
            error_log('denr_get_active_user_ids_by_role: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('denr_notify_user')) {
    function denr_notify_user(PDO $db, int $user_id, string $message, ?string $scheduled_time = null): void
    {
        if ($user_id <= 0) {
            return;
        }
        $message = trim($message);
        if ($message === '') {
            return;
        }
        try {
            if ($scheduled_time !== null && denr_notifications_has_scheduled_column($db)) {
                $ins = $db->prepare(
                    'INSERT INTO notifications (user_id, message, created_at, scheduled_time, is_read) VALUES (?, ?, NOW(), ?, 0)'
                );
                $ins->execute([$user_id, $message, $scheduled_time]);
            } else {
                $ins = $db->prepare(
                    'INSERT INTO notifications (user_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)'
                );
                $ins->execute([$user_id, $message]);
            }
        } catch (Throwable $e) {
            error_log('denr_notify_user: ' . $e->getMessage());
        }
    }
}

if (!function_exists('denr_notify_users')) {
    function denr_notify_users(PDO $db, array $user_ids, string $message, ?string $scheduled_time = null): void
    {
        $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids), static fn ($id) => $id > 0)));
        foreach ($user_ids as $uid) {
            denr_notify_user($db, $uid, $message, $scheduled_time);
        }
    }
}

if (!function_exists('denr_notify_role')) {
    function denr_notify_role(PDO $db, string $role, string $message, ?string $scheduled_time = null): void
    {
        denr_notify_users($db, denr_get_active_user_ids_by_role($db, $role), $message, $scheduled_time);
    }
}

if (!function_exists('denr_notify_admins')) {
    function denr_notify_admins(PDO $db, string $message): void
    {
        denr_notify_role($db, 'admin', $message);
    }
}

if (!function_exists('denr_notify_verifiers')) {
    function denr_notify_verifiers(PDO $db, string $message, ?string $scheduled_time = null): void
    {
        denr_notify_role($db, 'verifier', $message, $scheduled_time);
    }
}

if (!function_exists('denr_notify_staff')) {
    function denr_notify_staff(PDO $db, string $message, ?string $scheduled_time = null): void
    {
        denr_notify_admins($db, $message);
        denr_notify_verifiers($db, $message, $scheduled_time);
    }
}

if (!function_exists('denr_notify_admins_verifier_activity')) {
    function denr_notify_admins_verifier_activity(PDO $db, string $activity_description): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!isset($_SESSION['role'], $_SESSION['user_id']) || $_SESSION['role'] !== 'verifier') {
            return;
        }
        $verifier_id = (int) $_SESSION['user_id'];
        if ($verifier_id <= 0) {
            return;
        }
        $detail = trim($activity_description);
        if ($detail === '') {
            return;
        }
        $label = isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : '';
        if ($label === '') {
            $label = 'Verifier #' . $verifier_id;
        }
        denr_notify_admins($db, 'Verifier ' . $label . ': ' . $detail);
    }
}

if (!function_exists('denr_permit_type_label')) {
    function denr_permit_type_label(string $permit_type): string
    {
        return $permit_type === 'certificate' ? 'Certificate permit' : 'Cutting permit';
    }
}
