(function () {
    'use strict';

    // ---------- Toast notifications ----------
    window.showToast = function (message, type) {
        type = type || 'error';
        var container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            container.id = 'toastContainer';
            document.body.appendChild(container);
        }
        var el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.textContent = message;
        container.appendChild(el);
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 4200);
    };

    // ---------- Header blur on scroll ----------
    var header = document.getElementById('siteHeader');
    if (header) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 10) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // ---------- Notifications dropdown ----------
    var bellBtn = document.getElementById('bellBtn');
    var notifDropdown = document.getElementById('notifDropdown');

    if (bellBtn && notifDropdown) {
        bellBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('open');
        });

        document.addEventListener('click', function () {
            notifDropdown.classList.remove('open');
        });

        notifDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    // ---------- Admin nav dropdown ----------
    var adminBtn = document.getElementById("adminDropdownBtn");
    var adminMenu = document.getElementById("adminDropdownMenu");

    if (adminBtn && adminMenu) {
        adminBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            adminMenu.classList.toggle("open");
        });

        document.addEventListener("click", function () {
            adminMenu.classList.remove("open");
        });

        adminMenu.addEventListener("click", function (e) {
            e.stopPropagation();
        });
    }

    // ---------- Auto-dismiss alerts ----------
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
            setTimeout(function () {
                alert.style.transition = 'opacity 0.4s ease';
                alert.style.opacity = '0';
                setTimeout(function () {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 400);
            }, 5000);
        }
    });

    // ---------- Active nav highlight ----------
    var currentPath = window.location.pathname;
    var navLinks = document.querySelectorAll('.main-nav a');
    navLinks.forEach(function (link) {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });

    // ---------- Dark Mode ----------
    var themeToggle = document.getElementById('themeToggle');
    var themeIcon = document.getElementById('themeIcon');

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        if (themeIcon) {
            themeIcon.innerHTML = theme === 'dark'
                ? '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'
                : '<circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>';
        }
    }

    var savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        setTheme(savedTheme);
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        setTheme('dark');
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme');
            setTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    // ---------- Quill Editor ----------
    var quillInstances = [];

    function initQuill(containerEl) {
        if (typeof Quill === 'undefined') return;
        var editors = containerEl ? containerEl.querySelectorAll('.quill-editor') : document.querySelectorAll('.quill-editor');
        editors.forEach(function (el) {
            if (el._quillInitialized) return;
            el._quillInitialized = true;

            var targetName = el.getAttribute('data-target');
            var placeholder = el.getAttribute('data-placeholder') || 'Escribe aqui...';
            var existingContent = el.getAttribute('data-content') || '';

            var quill = new Quill(el, {
                theme: 'snow',
                placeholder: placeholder,
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                        ['blockquote', 'code-block'],
                        ['link'],
                        ['clean']
                    ]
                }
            });

            if (existingContent) {
                quill.root.innerHTML = existingContent;
            }

            // Restore draft
            var draftKey = 'draft_' + targetName;
            var draft = localStorage.getItem(draftKey);
            if (draft && !existingContent) {
                quill.root.innerHTML = draft;
            }

            // Autosave + sync hidden input
            var debounceTimer = null;
            var hiddenInput = el.closest('form') ? el.closest('form').querySelector('input[name="' + targetName + '"]') : null;
            quill.on('text-change', function () {
                var html = quill.root.innerHTML;
                if (hiddenInput) {
                    hiddenInput.value = html;
                }
                if (debounceTimer) clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    if (html !== '<p><br></p>' && html !== '') {
                        localStorage.setItem(draftKey, html);
                    } else {
                        localStorage.removeItem(draftKey);
                    }
                }, 800);
            });

            quillInstances.push(quill);
        });
    }

    function syncQuillBeforeSubmit(form) {
        var quillEditors = form ? form.querySelectorAll('.quill-editor') : document.querySelectorAll('.quill-editor');
        quillEditors.forEach(function (el) {
            var targetName = el.getAttribute('data-target');
            if (!targetName) return;
            var hidden = form ? form.querySelector('input[name="' + targetName + '"]') : document.querySelector('input[name="' + targetName + '"]');
            if (hidden) {
                var instance = quillInstances.find(function (q) { return q.container === el; });
                if (instance) {
                    hidden.value = instance.root.innerHTML;
                }
            }
        });
    }

    // ---------- Autoguardado (textareas no-Quill) ----------
    function setupTextareaAutosave(textarea, key) {
        if (!textarea) return;
        var draft = localStorage.getItem(key);
        if (draft && !textarea.value) {
            textarea.value = draft;
        }
        var debounceTimer = null;
        textarea.addEventListener('input', function () {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                if (textarea.value.trim() !== '') {
                    localStorage.setItem(key, textarea.value);
                } else {
                    localStorage.removeItem(key);
                }
            }, 800);
        });
    }

    function clearDraft(key) {
        localStorage.removeItem(key);
    }

    // Init Quill editors on page load
    initQuill();

    // Autoguardado for regular inputs
    setupTextareaAutosave(document.getElementById('titulo'), 'draft_titulo');

    // Sync Quill before any form submission
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.tagName === 'FORM') {
            syncQuillBeforeSubmit(form);
        }
    });

    // ---------- AJAX Comments (event delegation) ----------
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.id === 'formComentario') {
            e.preventDefault();
            var btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Enviando...';

            // Ensure Quill content is synced
            syncQuillBeforeSubmit(form);

            var formData = new FormData(form);

            // Show skeleton
            var container = document.getElementById('listaComentarios');
            if (container) {
                container.innerHTML = '<div class="skeleton skeleton-card" style="height:80px;margin-bottom:12px"></div><div class="skeleton skeleton-card" style="height:80px"></div>';
            }

            fetch(form.getAttribute('action') || window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                if (container) {
                    container.innerHTML = html;
                    // Re-init Quill in the new content
                    initQuill(container);
                    // Clear draft
                    clearDraft('draft_mensaje');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = 'Registrar Avance';
                showToast('Error al enviar el comentario.', 'error');
            });
        }
    });

    // ---------- Live Search ----------
    var buscador = document.getElementById('buscadorTickets');
    if (buscador) {
        buscador.addEventListener('input', function () {
            var query = this.value.toLowerCase().trim();
            var cards = document.querySelectorAll('.kanban-card, .ticket-grid-card');
            cards.forEach(function (card) {
                var text = card.textContent.toLowerCase();
                if (query === '' || text.indexOf(query) !== -1) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // ---------- Kanban Drag & Drop ----------
    var kanbanContainers = document.querySelectorAll('.kanban-column-body');
    if (kanbanContainers.length > 0 && typeof Sortable !== 'undefined') {
        kanbanContainers.forEach(function (container) {
            if (container.classList.contains('task-column-body')) return;
            new Sortable(container, {
                group: 'tickets',
                animation: 200,
                ghostClass: 'kanban-card-ghost',
                chosenClass: 'kanban-card-chosen',
                onMove: function (evt) {
                    var toEstado = evt.to.getAttribute('data-estado');
                    var board = document.getElementById('kanbanBoard');
                    if (toEstado === 'cerrado' && board && board.getAttribute('data-user-rol') !== 'admin') {
                        window.showToast('Solo administradores pueden cerrar tickets.', 'error');
                        return false;
                    }
                    return true;
                },
                onEnd: function (evt) {
                    var fromEstado = evt.from.getAttribute('data-estado');
                    var toEstado = evt.to.getAttribute('data-estado');
                    if (fromEstado !== toEstado) {
                        var ticketId = evt.item.getAttribute('data-ticket-id');
                        if (!ticketId) return;
                        var board = document.getElementById('kanbanBoard');
                        if (!board) return;
                        var csrfToken = board.getAttribute('data-csrf-token');

                        fetch('/helpdesk/ajax_actualizar_estado.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                ticket_id: ticketId,
                                estado: toEstado,
                                csrf_token: csrfToken
                            })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) {
                                updateKanbanCounts();
                                updateChartEstado();
                            } else {
                                showToast(data.error || 'No se pudo actualizar el estado.', 'error');
                                location.reload();
                            }
                        })
                        .catch(function () {
                            showToast('Error de conexion al actualizar el estado.', 'error');
                            location.reload();
                        });
                    }
                }
            });
        });
    }

    function updateKanbanCounts() {
        var counts = {
            abierto: 0,
            en_progreso: 0,
            resuelto: 0,
            cerrado: 0,
            urgente: 0
        };

        document.querySelectorAll('.kanban-column').forEach(function (col) {
            var estado = col.getAttribute('data-estado');
            var cards = col.querySelectorAll('.kanban-card');
            var count = cards.length;
            counts[estado] = count;

            var badge = col.querySelector('.kanban-column-header .badge');
            if (badge) {
                badge.textContent = count;
            }

            cards.forEach(function (card) {
                if (card.textContent.indexOf('urgente') !== -1 || card.querySelector('.badge-urgente')) {
                    counts.urgente++;
                }
            });
        });

        var total = counts.abierto + counts.en_progreso + counts.resuelto + counts.cerrado;

        var statsEl = document.querySelector('.stats-grid');
        if (statsEl) {
            var statNumbers = statsEl.querySelectorAll('.stat-number');
            if (statNumbers.length >= 5) {
                statNumbers[0].textContent = counts.abierto;
                statNumbers[1].textContent = counts.en_progreso;
                statNumbers[2].textContent = counts.resuelto;
                statNumbers[3].textContent = counts.urgente;
                statNumbers[4].textContent = total;
            }
        }
    }

    // ---------- Skeleton Loader ----------
    var skeletonEl = document.getElementById('kanbanSkeleton');
    var kanbanBoard = document.getElementById('kanbanBoard');
    if (skeletonEl && kanbanBoard) {
        setTimeout(function () {
            skeletonEl.style.opacity = '0';
            setTimeout(function () {
                skeletonEl.style.display = 'none';
                kanbanBoard.style.display = '';
            }, 300);
        }, 500);
    }

    // ---------- Clear drafts on success ----------
    var successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        var forms = document.querySelectorAll('form');
        if (forms.length > 0) {
            clearDraft('draft_titulo');
            clearDraft('draft_descripcion');
            clearDraft('draft_mensaje');
        }
    }

    // ---------- Charts ----------
    var chartEstadoInstance = null;
    var colorMap = { abierto: '#f59e0b', en_progreso: '#3b82f6', resuelto: '#10b981', cerrado: '#94a3b8' };

    if (typeof Chart !== 'undefined' && typeof chartData !== 'undefined') {
        var textColor = getComputedStyle(document.documentElement).getPropertyValue('--color-text-secondary').trim() || '#6c757d';
        var gridColor = getComputedStyle(document.documentElement).getPropertyValue('--color-border').trim() || '#e9ecef';
        Chart.defaults.color = textColor;
        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.font.size = 11;
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0,0,0,0.8)';
        Chart.defaults.plugins.tooltip.padding = 8;
        Chart.defaults.plugins.tooltip.cornerRadius = 6;
        Chart.defaults.plugins.tooltip.titleFont = { size: 12, weight: '600' };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 11 };

        var mesesLabels = chartData.meses.labels.map(function (l) {
            var parts = l.split('-');
            var months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            return months[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
        });

        var ctxMeses = document.getElementById('chartMeses');
        if (ctxMeses && chartData.meses.labels.length > 0) {
            new Chart(ctxMeses, {
                type: 'bar',
                data: {
                    labels: mesesLabels,
                    datasets: [{ data: chartData.meses.data, backgroundColor: '#3b82f6', borderRadius: 3, borderSkipped: false, barPercentage: 0.55 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                        y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: gridColor, drawBorder: false } }
                    }
                }
            });
        }

        var ctxEstado = document.getElementById('chartEstado');
        if (ctxEstado && chartData.estado.labels.length > 0) {
            chartEstadoInstance = new Chart(ctxEstado, {
                type: 'doughnut',
                data: {
                    labels: chartData.estado.labels,
                    datasets: [{ data: chartData.estado.data, backgroundColor: chartData.estado.colors, borderWidth: 2, borderColor: '#fff' }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '72%',
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, pointStyle: 'circle', font: { size: 11 } } },
                        tooltip: { callbacks: { label: function (ctx) { return ctx.parsed + ' tickets'; } } }
                    }
                }
            });
        }

        var ctxPrioridad = document.getElementById('chartPrioridad');
        if (ctxPrioridad && chartData.prioridad.labels.length > 0) {
            new Chart(ctxPrioridad, {
                type: 'bar',
                data: {
                    labels: chartData.prioridad.labels,
                    datasets: [{ data: chartData.prioridad.data, backgroundColor: chartData.prioridad.colors, borderRadius: 3, borderSkipped: false, barPercentage: 0.55 }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: gridColor, drawBorder: false } },
                        y: { grid: { display: false }, ticks: { font: { size: 10 } } }
                    }
                }
            });
        }
    }

    function updateChartEstado() {
        if (!chartEstadoInstance) return;
        fetch('/helpdesk/ajax_estadisticas.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.estado) {
                    var labels = [];
                    var values = [];
                    var colors = [];
                    data.estado.forEach(function (item) {
                        labels.push(item.label);
                        values.push(item.value);
                        var key = item.label.toLowerCase().replace(' ', '_');
                        colors.push(colorMap[key] || '#94a3b8');
                    });
                    chartEstadoInstance.data.labels = labels;
                    chartEstadoInstance.data.datasets[0].data = values;
                    chartEstadoInstance.data.datasets[0].backgroundColor = colors;
                    chartEstadoInstance.update();
                }
            })
            .catch(function () {
                showToast('Error al cargar estadisticas.', 'error');
            });
    }

    // ---------- Task Manager: Kanban Drag & Drop ----------
    var taskBoard = document.getElementById('taskKanbanBoard');
    if (taskBoard && typeof Sortable !== 'undefined') {
        var taskContainers = taskBoard.querySelectorAll('.task-column-body');
        taskContainers.forEach(function (container) {
            new Sortable(container, {
                group: 'tasks',
                animation: 200,
                ghostClass: 'kanban-card-ghost',
                chosenClass: 'kanban-card-chosen',
                onEnd: function (evt) {
                    var fromEstado = evt.from.getAttribute('data-estado');
                    var toEstado = evt.to.getAttribute('data-estado');
                    if (fromEstado !== toEstado) {
                        var taskId = evt.item.getAttribute('data-task-id');
                        if (!taskId) return;
                        var csrfToken = taskBoard.getAttribute('data-csrf-token');

                        // Optimistic count update
                        var fromBadge = evt.from.closest('.kanban-column').querySelector('.kanban-column-header .badge');
                        var toBadge = evt.to.closest('.kanban-column').querySelector('.kanban-column-header .badge');
                        if (fromBadge) fromBadge.textContent = parseInt(fromBadge.textContent) - 1;
                        if (toBadge) toBadge.textContent = parseInt(toBadge.textContent) + 1;

                        fetch('/helpdesk/ajax_tarea_estado.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                tarea_id: taskId,
                                estado: toEstado,
                                csrf_token: csrfToken
                            })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data.success) {
                                showToast('Error al mover la tarea.', 'error');
                                location.reload();
                            }
                        })
                        .catch(function () {
                            showToast('Error de conexion al mover la tarea.', 'error');
                            location.reload();
                        });
                    }
                }
            });
        });
    }

    // ---------- Task Manager: Subtask AJAX toggle ----------
    document.addEventListener('change', function (e) {
        var checkbox = e.target;
        if (checkbox && checkbox.classList.contains('subtask-checkbox')) {
            var item = checkbox.closest('.subtask-item');
            if (!item) return;
            var subtareaId = item.getAttribute('data-id');
            if (!subtareaId) return;

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('accion', 'toggle');
            formData.append('subtarea_id', subtareaId);

            fetch('/helpdesk/ajax_tarea_subtarea.php', {
                method: 'POST',
                body: formData
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    var span = item.querySelector('.subtask-label span');
                    if (span) {
                        span.classList.toggle('completed', data.completado === 1);
                    }
                    // Update progress bars
                    var bars = document.querySelectorAll('.subtask-progress-fill');
                    bars.forEach(function (bar) {
                        bar.style.width = data.porcentaje + '%';
                    });
                    // Update counters
                    var counters = document.querySelectorAll('.card-header .text-muted.text-small');
                    counters.forEach(function (c) {
                        if (c.textContent.indexOf('/') !== -1) {
                            c.textContent = data.completadas + '/' + data.total;
                        }
                    });
                }
            })
            .catch(function () {
                showToast('Error al actualizar subtarea.', 'error');
            });
        }
    });

    // ---------- Task Manager: Subtask delete (AJAX) ----------
    document.addEventListener('click', function (e) {
        var btn = e.target;
        if (btn && btn.classList.contains('subtask-delete')) {
            e.preventDefault();
            var item = btn.closest('.subtask-item');
            if (!item) return;
            var subtareaId = item.getAttribute('data-id');
            if (!subtareaId) return;

            if (!confirm('¿Eliminar este item?')) return;

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
            var formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('accion', 'eliminar');
            formData.append('subtarea_id', subtareaId);

            fetch('/helpdesk/ajax_tarea_subtarea.php', {
                method: 'POST',
                body: formData
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    item.remove();
                }
            })
            .catch(function () {
                showToast('Error al eliminar subtarea.', 'error');
            });
        }
    });

    // ---------- Task Manager: Timer tick (1s) ----------
    var timerDisplay = document.querySelector('.timer-display');
    if (timerDisplay) {
        var startTime = parseInt(timerDisplay.getAttribute('data-start'));
        if (startTime) {
            setInterval(function () {
                var elapsed = Math.floor((Date.now() / 1000) - startTime);
                var h = Math.floor(elapsed / 3600);
                var m = Math.floor((elapsed % 3600) / 60);
                var s = elapsed % 60;
                var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
                timerDisplay.textContent = (h > 0 ? h + ':' : '') + pad(m) + ':' + pad(s);
            }, 1000);
        }
    }

    // ---------- Task Manager: Active nav for tareas pages ----------
    var currentPath = window.location.pathname;
    if (currentPath.indexOf('/tareas') !== -1 || currentPath.indexOf('/tarea_') !== -1) {
        var navLinks = document.querySelectorAll('.main-nav a');
        navLinks.forEach(function (link) {
            if (link.getAttribute('href').indexOf('tareas') !== -1) {
                link.classList.add('active');
            }
        });
    }

})();
