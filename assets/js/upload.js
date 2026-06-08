(function () {
    'use strict';

    var dropZones = document.querySelectorAll('.drop-zone');

    dropZones.forEach(function (dropZone) {
        var fileInput = dropZone.querySelector('input[type="file"]');
        var previewList = document.getElementById(dropZone.getAttribute('data-preview'));

        // Click to open file dialog
        dropZone.addEventListener('click', function () {
            if (fileInput) fileInput.click();
        });

        // Drag events
        dropZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', function () {
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            if (e.dataTransfer.files.length > 0 && fileInput) {
                fileInput.files = e.dataTransfer.files;
                handleFiles(fileInput.files, dropZone, previewList);
            }
        });

        // File input change
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                handleFiles(this.files, dropZone, previewList);
            });
        }
    });

    function handleFiles(files, dropZone, previewList) {
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            if (!validateFile(file)) continue;
            previewFile(file, previewList);
            uploadFile(file, dropZone, previewList);
        }
    }

    function validateFile(file) {
        var maxSize = 10 * 1024 * 1024; // 10 MB
        var allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', 'application/x-rar-compressed',
            'text/plain'
        ];

        if (file.size > maxSize) {
            if (window.showToast) showToast('El archivo "' + file.name + '" excede el limite de 10 MB.', 'error');
            return false;
        }

        if (allowedTypes.indexOf(file.type) === -1 && file.type !== '') {
            if (window.showToast) showToast('El tipo de archivo "' + file.name + '" no esta permitido.', 'error');
            return false;
        }

        return true;
    }

    function previewFile(file, previewList) {
        if (!previewList) return;

        var item = document.createElement('div');
        item.className = 'file-preview-item';
        item.id = 'upload-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5);

        // Thumbnail or icon
        var isImage = file.type.indexOf('image/') === 0;
        if (isImage) {
            var img = document.createElement('img');
            img.className = 'file-thumb';
            img.alt = file.name;
            var reader = new FileReader();
            reader.onload = function (e) {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
            item.appendChild(img);
        } else {
            var icon = document.createElement('div');
            icon.className = 'file-icon';
            icon.textContent = getFileIcon(file.name);
            item.appendChild(icon);
        }

        // File name
        var nameEl = document.createElement('div');
        nameEl.className = 'file-name';
        nameEl.textContent = file.name;
        item.appendChild(nameEl);

        // Progress bar
        var progressWrap = document.createElement('div');
        progressWrap.className = 'upload-progress';
        var progressBar = document.createElement('div');
        progressBar.className = 'progress-bar';
        progressWrap.appendChild(progressBar);
        item.appendChild(progressWrap);

        // Remove button
        var removeBtn = document.createElement('button');
        removeBtn.className = 'file-remove';
        removeBtn.textContent = '×';
        removeBtn.title = 'Eliminar';
        removeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            item.remove();
        });
        item.appendChild(removeBtn);

        previewList.appendChild(item);
    }

    function uploadFile(file, dropZone, previewList) {
        var ticketId = dropZone.getAttribute('data-ticket-id');
        if (!ticketId) return;

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (!csrfMeta) return;

        var formData = new FormData();
        formData.append('archivo', file);
        formData.append('ticket_id', ticketId);
        formData.append('csrf_token', csrfMeta.getAttribute('content'));

        var xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 100);
                updateProgress(previewList, file, percent);
            }
        });

        xhr.addEventListener('load', function () {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        markComplete(previewList, file);
                    } else {
                        markError(previewList, file, data.error || 'Error al subir.');
                    }
                } catch (e) {
                    markError(previewList, file, 'Error de respuesta del servidor.');
                }
            } else {
                markError(previewList, file, 'Error del servidor (' + xhr.status + ').');
            }
        });

        xhr.addEventListener('error', function () {
            markError(previewList, file, 'Error de conexion.');
        });

        xhr.open('POST', window.BASE_URL + '/subir_archivo.php', true);
        xhr.send(formData);
    }

    function updateProgress(previewList, file, percent) {
        if (!previewList) return;
        var items = previewList.querySelectorAll('.file-preview-item');
        for (var i = 0; i < items.length; i++) {
            var nameEl = items[i].querySelector('.file-name');
            if (nameEl && nameEl.textContent === file.name) {
                var bar = items[i].querySelector('.progress-bar');
                if (bar) bar.style.width = percent + '%';
                break;
            }
        }
    }

    function markComplete(previewList, file) {
        if (!previewList) return;
        var items = previewList.querySelectorAll('.file-preview-item');
        for (var i = 0; i < items.length; i++) {
            var nameEl = items[i].querySelector('.file-name');
            if (nameEl && nameEl.textContent === file.name) {
                var progress = items[i].querySelector('.upload-progress');
                if (progress) progress.remove();
                var removeBtn = items[i].querySelector('.file-remove');
                if (removeBtn) removeBtn.style.opacity = '1';
                break;
            }
        }
    }

    function markError(previewList, file, error) {
        if (!previewList) return;
        var items = previewList.querySelectorAll('.file-preview-item');
        for (var i = 0; i < items.length; i++) {
            var nameEl = items[i].querySelector('.file-name');
            if (nameEl && nameEl.textContent === file.name) {
                var bar = items[i].querySelector('.progress-bar');
                if (bar) bar.classList.add('error');
                nameEl.textContent = 'Error: ' + file.name;
                nameEl.style.color = 'var(--color-danger)';
                break;
            }
        }
        if (window.showToast) showToast(error, 'error');
    }

    function getFileIcon(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        var icons = {
            pdf: '📄',
            doc: '📝', docx: '📝',
            xls: '📊', xlsx: '📊',
            zip: '📦', rar: '📦',
            txt: '📃',
            mp4: '🎬', avi: '🎬', mov: '🎬',
            mp3: '🎵', wav: '🎵'
        };
        return icons[ext] || '📎';
    }

})();
