<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Calendrier — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <!-- FullCalendar 6 n'a pas de CSS séparé, les styles sont dans le JS -->
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine: #002147; }
        .main-content { min-height: 100vh; background: #f4f7fe; padding: 24px 28px; }

        #calendar { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .fc-toolbar-title { font-size: 1.1rem !important; font-weight: 700; color: var(--marine); }
        .fc-button-primary { background-color: var(--marine) !important; border-color: var(--marine) !important; }
        .fc-button-primary:not(:disabled):active,
        .fc-button-primary:not(:disabled).fc-button-active { background-color: #001030 !important; }
        .fc-event { cursor: pointer; font-size: 0.78rem; border-radius: 4px !important; border: none !important; padding: 2px 5px; }
        .fc-day-today { background: rgba(0,33,71,.06) !important; }

        /* Légende */
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }

        /* Tooltip */
        #eventTooltip { position: fixed; z-index: 9999; display: none; min-width: 220px; }
    </style>
</head>
<body>

<?php include('../includes/sidebar.php'); ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-light text-dark border"><span class="legend-dot bg-danger me-1"></span>Loyer en retard</span>
            <span class="badge bg-light text-dark border"><span class="legend-dot me-1" style="background:#fd7e14"></span>Loyer &lt; 7j</span>
            <span class="badge bg-light text-dark border"><span class="legend-dot bg-primary me-1"></span>Échéance</span>
            <span class="badge bg-light text-dark border"><span class="legend-dot bg-success me-1"></span>Visite confirmée</span>
            <span class="badge bg-light text-dark border"><span class="legend-dot bg-warning me-1"></span>Visite en attente</span>
            <span class="badge bg-light text-dark border"><span class="legend-dot me-1" style="background:#6f42c1"></span>Fin de contrat</span>
        </div>
    </div>

    <div id="calendar"></div>
</div>

<!-- Tooltip détail événement -->
<div id="eventTooltip" class="card border-0 shadow-lg p-3" style="min-width:240px">
    <div id="tooltipTitle" class="fw-bold mb-1"></div>
    <div id="tooltipMaison" class="text-muted small mb-1"></div>
    <div id="tooltipExtra" class="small"></div>
    <a id="tooltipLink" href="#" class="btn btn-sm btn-outline-primary mt-2 w-100">Voir le détail</a>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script src="../js/fullcalendar.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    const tooltip = document.getElementById('eventTooltip');

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        locale: 'fr',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,listWeek'
        },
        height: 'auto',
        events: '../php/get_events.php',
        eventDidMount(info) {
            // Affiche le tooltip au survol
            info.el.addEventListener('mouseenter', (e) => {
                const p = info.event.extendedProps;
                document.getElementById('tooltipTitle').textContent   = info.event.title.replace(/^.{2}/, '');
                document.getElementById('tooltipMaison').textContent  = p.maison ?? '';
                var tooltipExtra = document.getElementById('tooltipExtra');
                tooltipExtra.innerHTML = '';
                if (p.tel) {
                    var icon = document.createElement('i');
                    icon.className = 'fa fa-phone me-1';
                    tooltipExtra.appendChild(icon);
                    tooltipExtra.appendChild(document.createTextNode(p.tel));
                } else if (p.statut) {
                    tooltipExtra.textContent = 'Statut : ' + p.statut;
                }
                document.getElementById('tooltipLink').href = p.url ?? '#';

                tooltip.style.display = 'block';
                tooltip.style.left = Math.min(e.clientX + 12, window.innerWidth - 260) + 'px';
                tooltip.style.top  = (e.clientY + 12) + 'px';
            });
            info.el.addEventListener('mouseleave', () => {
                tooltip.style.display = 'none';
            });
        },
        eventClick(info) {
            const url = info.event.extendedProps.url;
            if (url) window.location.href = url;
        },
        noEventsContent: 'Aucun événement ce mois-ci.',
    });

    calendar.render();

    // Masquer tooltip au scroll
    document.addEventListener('scroll', () => { tooltip.style.display = 'none'; }, true);
});
</script>
</body>
</html>
