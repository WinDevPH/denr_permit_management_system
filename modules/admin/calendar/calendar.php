<?php
session_start();
// Admin only (no verifier role)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit();
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Create verification_assignments table if it does not exist
$db->exec("CREATE TABLE IF NOT EXISTS `verification_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `verifier_id` int(11) NOT NULL,
  `plantation_id` int(11) DEFAULT NULL,
  `permit_id` int(11) DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `verifier_id` (`verifier_id`),
  KEY `plantation_id` (`plantation_id`),
  KEY `permit_id` (`permit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$assignments = [];
$query = "SELECT va.*, pl.plantation_name, pl.location_address, u.full_name as owner_name,
          pm.permit_type, pm.permit_id, uv.full_name as verifier_name
          FROM verification_assignments va
          LEFT JOIN plantations pl ON va.plantation_id = pl.plantation_id
          LEFT JOIN users u ON pl.user_id = u.user_id
          LEFT JOIN users uv ON va.verifier_id = uv.user_id
          LEFT JOIN permits pm ON va.permit_id = pm.permit_id
          ORDER BY va.scheduled_at ASC";
try {
    $stmt = $db->query($query);
    $assignments = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $assignments = [];
}

$verifiers = [];
$plantations_for_schedule = [];
$verifier_district_dropdown = [];
try {
    $verifiers = $db->query("SELECT user_id, full_name, email, COALESCE(district,'') AS district FROM users WHERE role = 'verifier' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    try {
        $verifiers = $db->query("SELECT user_id, full_name, email, '' AS district FROM users WHERE role = 'verifier' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e2) {
        $verifiers = [];
    }
}
try {
    $vdd = $db->query(
        "SELECT DISTINCT TRIM(district) AS d FROM users
         WHERE role = 'verifier' AND district IS NOT NULL AND TRIM(district) != ''
         ORDER BY d ASC"
    );
    $verifier_district_dropdown = $vdd ? array_values(array_filter(array_map('trim', $vdd->fetchAll(PDO::FETCH_COLUMN, 0) ?: []))) : [];
} catch (PDOException $e) {
    $verifier_district_dropdown = [];
}
try {
    $plantations_for_schedule = $db->query(
        "SELECT p.plantation_id, p.plantation_name, p.location_address, p.status, COALESCE(p.district,'') AS district, u.full_name AS owner_name
         FROM plantations p
         JOIN users u ON p.user_id = u.user_id
         WHERE p.status IN ('validated', 'verified', 'registered')
         ORDER BY p.registered_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    try {
        $plantations_for_schedule = $db->query(
            "SELECT p.plantation_id, p.plantation_name, p.location_address, p.status, '' AS district, u.full_name AS owner_name
             FROM plantations p
             JOIN users u ON p.user_id = u.user_id
             WHERE p.status IN ('validated', 'verified', 'registered')
             ORDER BY p.registered_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e2) {
        $plantations_for_schedule = [];
    }
}

// Pass assignments as JSON for calendar JS
$assignmentsJson = json_encode(array_map(function ($a) {
    return [
        'id' => (int) $a['id'],
        'scheduled_at' => $a['scheduled_at'],
        'type' => $a['plantation_id'] ? 'land' : 'permit',
        'title' => $a['plantation_name'] ? $a['plantation_name'] : 'Permit #' . $a['permit_id'],
        'owner' => $a['owner_name'] ?? '-',
        'location' => $a['location_address'] ?? '-',
        'status' => $a['status'],
        'verifier' => $a['verifier_name'] ?? '-',
        'notes' => $a['notes'] ?? '',
    ];
}, $assignments));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/calendar.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>
<body class="admin-calendar-page">
    <div class="dashboard-container">
        <?php include '../../../admin_includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include '../../../admin_includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard admin-calendar">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Calendar</h1>
                        <p class="admin-dashboard-subtitle">Schedule verifiers to visit landowner plantations after admin has Checked their details. Verifiers are notified and see details on their calendar.</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleVerificationModal">
                        <i class="fas fa-calendar-plus"></i> Schedule plantation verification
                    </button>
                </header>

                <div class="calendar-toolbar">
                    <div class="calendar-nav">
                        <button type="button" class="calendar-nav-btn" id="calendarPrev" title="Previous month" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
                        <h2 class="calendar-month-title" id="calendarMonthTitle">Month Year</h2>
                        <button type="button" class="calendar-nav-btn" id="calendarNext" title="Next month" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <button type="button" class="calendar-today-btn" id="calendarToday"><i class="fas fa-calendar-day"></i> Today</button>
                </div>

                <section class="calendar-card">
                    <div class="calendar-wrap">
                        <div class="calendar-weekdays">
                            <div class="calendar-weekday">Sun</div>
                            <div class="calendar-weekday">Mon</div>
                            <div class="calendar-weekday">Tue</div>
                            <div class="calendar-weekday">Wed</div>
                            <div class="calendar-weekday">Thu</div>
                            <div class="calendar-weekday">Fri</div>
                            <div class="calendar-weekday">Sat</div>
                        </div>
                        <div class="calendar-days" id="calendarDays"></div>
                    </div>
                </section>

                <section class="calendar-schedule-card">
                    <header class="calendar-schedule-header">
                        <h2 class="calendar-schedule-title"><i class="fas fa-list"></i> Schedule list</h2>
                        <span class="calendar-schedule-count" id="assignmentCount">0</span>
                    </header>
                    <div class="calendar-schedule-body">
                        <?php if (empty($assignments)): ?>
                        <div class="calendar-empty-state">
                            <div class="calendar-empty-icon"><i class="fas fa-calendar-check"></i></div>
                            <h3 class="calendar-empty-title">No assignments yet</h3>
                            <p class="calendar-empty-text">Verification assignments will appear here and on the calendar when scheduled.</p>
                        </div>
                        <?php else: ?>
                        <div class="calendar-table-wrap">
                            <table class="calendar-table">
                                <thead>
                                    <tr>
                                        <th>Date & time</th>
                                        <th>Verifier</th>
                                        <th>Type</th>
                                        <th>Plantation / permit</th>
                                        <th>Owner</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $a): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($a['scheduled_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($a['verifier_name'] ?? '-'); ?></td>
                                        <td><?php echo $a['plantation_id'] ? 'Land verification' : 'Permit verification'; ?></td>
                                        <td><?php echo $a['plantation_name'] ? htmlspecialchars($a['plantation_name']) : 'Permit #' . $a['permit_id']; ?></td>
                                        <td><?php echo htmlspecialchars($a['owner_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($a['location_address'] ?? '-'); ?></td>
                                        <td><span class="calendar-status-badge status-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div class="modal fade calendar-day-modal" id="dayModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dayModalTitle">Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="dayModalBody"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleVerificationModal" tabindex="-1" aria-labelledby="scheduleVerificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleVerificationModalLabel"><i class="fas fa-user-check me-2"></i>Schedule plantation verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="scheduleVerificationForm" method="post" action="../../../handlers/save_verification_assignment.php">
                    <div class="modal-body">
                        <p class="text-muted small mb-3">Select <strong>one or more</strong> verifiers and a plantation. Each verifier is notified. Set each verifier&rsquo;s <em>District</em> under Manage Users for best filtering.</p>
                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <label class="form-label mb-1" for="verifierDistrictSelect">District</label>
                                <select class="form-select form-select-sm" id="verifierDistrictSelect" autocomplete="off">
                                    <option value="">All districts</option>
                                    <?php foreach ($verifier_district_dropdown as $vdd): ?>
                                    <?php $__v = strtolower(trim((string) $vdd)); ?>
                                    <option value="<?php echo htmlspecialchars($__v); ?>">
                                        <?php echo htmlspecialchars(trim((string) $vdd)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="small text-muted mb-0 mt-1">Example: pick <strong>District II</strong> to show only verifiers tagged with that district on Manage Users.</p>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label mb-1" for="verifierDistrictFilter">Optional: narrow by name or email</label>
                                <input type="text" class="form-control form-control-sm" id="verifierDistrictFilter" placeholder="Search…" autocomplete="off">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="schedule_verifier_id">Verifiers <span class="text-danger">*</span> <small class="text-muted">(Ctrl/Cmd+click for multiple)</small></label>
                            <select class="form-select" name="verifier_ids[]" id="schedule_verifier_id" multiple size="8" required>
                                <?php foreach ($verifiers as $v): ?>
                                <option value="<?php echo (int) $v['user_id']; ?>" data-district="<?php echo htmlspecialchars(strtolower($v['district'] ?? '')); ?>" data-label="<?php echo htmlspecialchars(strtolower($v['full_name'] . ' ' . ($v['email'] ?? ''))); ?>">
                                    <?php echo htmlspecialchars($v['full_name']); ?><?php echo ($v['district'] ?? '') !== '' ? ' — ' . htmlspecialchars($v['district']) : ''; ?><?php echo !empty($v['email']) ? ' · ' . htmlspecialchars($v['email']) : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($verifiers)): ?>
                            <p class="text-warning small mb-0 mt-1">No verifier accounts found. Create users with role &quot;verifier&quot; first.</p>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="schedule_plantation_id">Plantation (Checked only) <span class="text-danger">*</span></label>
                            <select class="form-select" name="plantation_id" id="schedule_plantation_id" required>
                                <option value="">— Select checked plantation —</option>
                                <?php if (empty($plantations_for_schedule)): ?>
                                <option value="" disabled>No checked plantations yet — review details first</option>
                                <?php endif; ?>
                                <?php foreach ($plantations_for_schedule as $pl): ?>
                                <option value="<?php echo (int) $pl['plantation_id']; ?>" data-district="<?php echo htmlspecialchars(strtolower($pl['district'] ?? '')); ?>">
                                    <?php
                                    $plStatusLabel = ($pl['status'] ?? '') === 'validated' ? 'Checked' : ucfirst((string) ($pl['status'] ?? ''));
                                    echo htmlspecialchars($pl['plantation_name']); ?> — <?php echo htmlspecialchars($pl['owner_name']); ?> (<?php echo htmlspecialchars($plStatusLabel); ?>)<?php echo ($pl['district'] ?? '') !== '' ? ' · ' . htmlspecialchars($pl['district']) : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="small text-muted mb-0 mt-1" id="scheduleDistrictHint"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="schedule_scheduled_at">Date & time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="scheduled_at" id="schedule_scheduled_at" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="schedule_notes">Notes for verifier (optional)</label>
                            <textarea class="form-control" name="notes" id="schedule_notes" rows="2" placeholder="Meeting point, access instructions, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="scheduleVerificationSubmit" <?php echo empty($verifiers) ? 'disabled' : ''; ?>>
                            <i class="fas fa-save"></i> Save schedule &amp; notify verifier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        const assignments = <?php echo $assignmentsJson; ?>;
        let currentDate = new Date();

        function esc(s) {
            if (s == null || s === '') return '';
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        const scheduleForm = document.getElementById('scheduleVerificationForm');
        if (scheduleForm) {
            const dtInput = document.getElementById('schedule_scheduled_at');
            if (dtInput && !dtInput.value) {
                const t = new Date();
                t.setMinutes(t.getMinutes() - t.getTimezoneOffset());
                dtInput.value = t.toISOString().slice(0, 16);
            }
            scheduleForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('scheduleVerificationSubmit');
                const fd = new FormData(scheduleForm);
                if (btn) { btn.disabled = true; }
                fetch('../../../handlers/save_verification_assignment.php', { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            showNotification('error', data.message || 'Could not save.');
                            if (btn) btn.disabled = false;
                        }
                    })
                    .catch(function() {
                        showNotification('error', 'Network error. Please try again.');
                        if (btn) btn.disabled = false;
                    });
            });

            var vDistrictSel = document.getElementById('verifierDistrictSelect');
            var vFilter = document.getElementById('verifierDistrictFilter');
            var vSel = document.getElementById('schedule_verifier_id');
            var pSel = document.getElementById('schedule_plantation_id');
            var hint = document.getElementById('scheduleDistrictHint');
            function applyVerifierFilter() {
                if (!vSel) return;
                var dq = vDistrictSel && vDistrictSel.value ? vDistrictSel.value.trim().toLowerCase() : '';
                var q = (vFilter && vFilter.value) ? vFilter.value.trim().toLowerCase() : '';
                Array.from(vSel.options).forEach(function(opt) {
                    if (!opt.value) return;
                    var d = (opt.getAttribute('data-district') || '').trim().toLowerCase();
                    var lab = opt.getAttribute('data-label') || '';
                    var distOk = !dq || d === dq;
                    var txtOk = !q || lab.indexOf(q) >= 0 || d.indexOf(q) >= 0;
                    opt.hidden = !(distOk && txtOk);
                });
            }
            if (vFilter) vFilter.addEventListener('input', applyVerifierFilter);
            if (vDistrictSel) {
                vDistrictSel.addEventListener('change', applyVerifierFilter);
            }
            if (pSel && hint) {
                pSel.addEventListener('change', function() {
                    var opt = pSel.options[pSel.selectedIndex];
                    var pd = opt && opt.getAttribute('data-district');
                    if (pd) {
                        hint.textContent = 'Tip: Prefer verifiers whose district matches this plantation (' + pd + '). Use the filter box above.';
                    } else {
                        hint.textContent = 'Add a district on the plantation record or verifier profile to enable location-based matching.';
                    }
                });
            }
        }

        const weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

        function formatDateKey(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + day;
        }

        function getAssignmentsForDay(dateKey) {
            return assignments.filter(function(a) {
                const scheduled = a.scheduled_at.slice(0, 10);
                return scheduled === dateKey;
            });
        }

        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            document.getElementById('calendarMonthTitle').textContent = monthNames[month] + ' ' + year;

            const first = new Date(year, month, 1);
            const last = new Date(year, month + 1, 0);
            const startDay = first.getDay();
            const daysInMonth = last.getDate();
            const prevMonth = new Date(year, month, 0);
            const prevDays = prevMonth.getDate();

            let html = '';
            const totalCells = Math.ceil((startDay + daysInMonth) / 7) * 7;
            for (let i = 0; i < totalCells; i++) {
                let dayNum, dateKey, isCurrentMonth, isToday;
                if (i < startDay) {
                    dayNum = prevDays - startDay + i + 1;
                    const d = new Date(year, month - 1, dayNum);
                    dateKey = formatDateKey(d);
                    isCurrentMonth = false;
                    isToday = false;
                } else {
                    dayNum = i - startDay + 1;
                    if (dayNum > daysInMonth) {
                        dayNum = dayNum - daysInMonth;
                        const d = new Date(year, month + 1, dayNum);
                        dateKey = formatDateKey(d);
                        isCurrentMonth = false;
                    } else {
                        const d = new Date(year, month, dayNum);
                        dateKey = formatDateKey(d);
                        isCurrentMonth = true;
                    }
                    const today = new Date();
                    isToday = formatDateKey(today) === dateKey;
                }
                const dayAssignments = getAssignmentsForDay(dateKey);
                const count = dayAssignments.length;
                const cls = ['calendar-day'];
                if (!isCurrentMonth) cls.push('calendar-day-other');
                if (isToday) cls.push('calendar-day-today');
                if (count > 0) cls.push('calendar-day-has-events');
                html += '<div class="' + cls.join(' ') + '" data-date="' + dateKey + '">';
                html += '<span class="calendar-day-num">' + dayNum + '</span>';
                if (count > 0) {
                    html += '<div class="calendar-day-events">';
                    dayAssignments.slice(0, 3).forEach(function(ev) {
                        html += '<div class="calendar-event" title="' + (ev.title || '') + '">' + (ev.type === 'land' ? 'L' : 'P') + ': ' + (ev.title || '').substring(0, 12) + (ev.title && ev.title.length > 12 ? '…' : '') + '</div>';
                    });
                    if (count > 3) html += '<div class="calendar-event-more">+' + (count - 3) + ' more</div>';
                    html += '</div>';
                }
                html += '</div>';
            }
            document.getElementById('calendarDays').innerHTML = html;

            document.querySelectorAll('.calendar-day').forEach(function(cell) {
                cell.addEventListener('click', function() {
                    const dateKey = this.getAttribute('data-date');
                    const dayAssignments = getAssignmentsForDay(dateKey);
                    const d = new Date(dateKey + 'T12:00:00');
                    document.getElementById('dayModalTitle').textContent = 'Schedule – ' + weekdays[d.getDay()] + ', ' + monthNames[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
                    let body = '';
                    if (dayAssignments.length === 0) {
                        body = '<p class="text-muted mb-0">No assignments this day.</p>';
                    } else {
                        body = '<ul class="list-group list-group-flush">';
                        dayAssignments.forEach(function(ev) {
                            var timeStr = ev.scheduled_at ? ev.scheduled_at.replace(' ', ' · ') : '';
                            body += '<li class="list-group-item">';
                            body += '<strong>' + (ev.type === 'land' ? 'Land verification' : 'Permit verification') + '</strong> — ' + esc(ev.title || '') + '<br>';
                            body += '<small class="text-muted d-block">Verifier: ' + esc(ev.verifier || '-') + '</small>';
                            body += '<small class="text-muted d-block">' + esc(timeStr) + '</small>';
                            body += '<small class="text-muted d-block">Owner: ' + esc(ev.owner || '') + ' · ' + esc(ev.location || '') + '</small>';
                            if (ev.notes) body += '<small class="d-block mt-1">' + esc(ev.notes) + '</small>';
                            body += '<span class="badge mt-1 bg-secondary">' + esc(ev.status || '') + '</span>';
                            body += '</li>';
                        });
                        body += '</ul>';
                    }
                    document.getElementById('dayModalBody').innerHTML = body;
                    new bootstrap.Modal(document.getElementById('dayModal')).show();
                });
            });
        }

        document.getElementById('calendarPrev').addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });
        document.getElementById('calendarNext').addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });
        document.getElementById('calendarToday').addEventListener('click', function() {
            currentDate = new Date();
            renderCalendar();
        });

        document.getElementById('assignmentCount').textContent = assignments.length;
        renderCalendar();
    })();
    </script>
</body>
</html>
