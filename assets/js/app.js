(function () {
    'use strict';

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

})();
