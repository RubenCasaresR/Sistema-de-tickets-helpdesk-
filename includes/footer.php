<?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
<footer style="text-align:center;padding:24px;font-size:0.78rem;color:var(--color-text-muted);border-top:1px solid var(--color-border-light);margin-top:auto;">
    &copy; <?= date('Y') ?> HelpDesk System &mdash; Todos los derechos reservados.
</footer>
<?php endif; ?>
</main>
<script src="/helpdesk/assets/js/app.js"></script>
</body>
</html>

