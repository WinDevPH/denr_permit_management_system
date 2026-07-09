<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'verifier') {
    header('Location: ../../../index.php');
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
$database = new Database();
$db = $database->getConnection();
$verifier_id = (int) $_SESSION['user_id'];

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
          pm.permit_type, pm.permit_id
          FROM verification_assignments va
          LEFT JOIN plantations pl ON va.plantation_id = pl.plantation_id
          LEFT JOIN users u ON pl.user_id = u.user_id
          LEFT JOIN permits pm ON va.permit_id = pm.permit_id
          WHERE va.verifier_id = :verifier_id
          ORDER BY va.scheduled_at ASC";
try {
    $stmt = $db->prepare($query);
    $stmt->bindParam(':verifier_id', $verifier_id, PDO::PARAM_INT);
    $stmt->execute();
    $assignments = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $assignments = [];
}

$assignmentsJson = json_encode(array_map(function ($a) {
    return [
        'id' => (int) $a['id'],
        'scheduled_at' => $a['scheduled_at'],
        'type' => $a['plantation_id'] ? 'land' : 'permit',
        'title' => $a['plantation_name'] ? $a['plantation_name'] : 'Permit #' . $a['permit_id'],
        'owner' => $a['owner_name'] ?? '-',
        'location' => $a['location_address'] ?? '-',
        'status' => $a['status'],
        'notes' => $a['notes'] ?? '',
    ];
}, $assignments));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - DENR Verifier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/calendar.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>
<body class="admin-calendar-page">
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../verifier_includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include __DIR__ . '/../../../verifier_includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard admin-calendar">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Calendar</h1>
                        <p class="admin-dashboard-subtitle">Your scheduled plantation and permit verifications. Open a day for details; admin-set visits also notify you in the header bell.</p>
                    </div>
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
                        <h2 class="calendar-schedule-title"><i class="fas fa-list"></i> My schedule</h2>
                        <span class="calendar-schedule-count" id="assignmentCount">0</span>
                    </header>
                    <div class="calendar-schedule-body">
                        <?php if (empty($assignments)): ?>
                        <div class="calendar-empty-state">
                            <div class="calendar-empty-icon"><i class="fas fa-calendar-check"></i></div>
                            <h3 class="calendar-empty-title">No assignments yet</h3>
                            <p class="calendar-empty-text">Your verification assignments will appear here when scheduled by admin.</p>
                        </div>
                        <?php else: ?>
                        <div class="calendar-table-wrap">
                            <table class="calendar-table">
                                <thead>
                                    <tr>
                                        <th>Date & time</th>
                                        <th>Type</th>
                                        <th>Plantation / permit</th>
                                        <th>Owner</th>
                                        <th>Location</th>
                                        <th>Notes</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $a): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($a['scheduled_at'])); ?></td>
                                        <td><?php echo $a['plantation_id'] ? 'Land verification' : 'Permit verification'; ?></td>
                                        <td><?php echo $a['plantation_name'] ? htmlspecialchars($a['plantation_name']) : 'Permit #' . $a['permit_id']; ?></td>
                                        <td><?php echo htmlspecialchars($a['owner_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($a['location_address'] ?? '-'); ?></td>
                                        <td><?php echo !empty($a['notes']) ? nl2br(htmlspecialchars($a['notes'])) : '—'; ?></td>
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
                            body += '<small class="text-muted d-block">' + esc(timeStr) + '</small>';
                            body += '<small class="text-muted d-block">Owner: ' + esc(ev.owner || '') + '</small>';
                            body += '<small class="text-muted d-block">Location: ' + esc(ev.location || '') + '</small>';
                            if (ev.notes) body += '<div class="small mt-2 p-2 bg-light rounded"><strong>Notes:</strong> ' + esc(ev.notes) + '</div>';
                            body += '<span class="badge mt-2 bg-secondary">' + esc(ev.status || '') + '</span>';
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
