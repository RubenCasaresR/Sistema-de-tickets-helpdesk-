<?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
<footer style="text-align:center;padding:24px;font-size:0.78rem;color:var(--color-text-muted);border-top:1px solid var(--color-border-light);margin-top:auto;">
    &copy; <?= date('Y') ?> HelpDesk System &mdash; Todos los derechos reservados.
</footer>
<?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= url('assets/js/upload.js') ?>"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>

