// Video Drag & Drop Uploader with Instant Preview, Duration Validation, and Robust Attachment
document.addEventListener('DOMContentLoaded', () => {
    const dropzone = document.getElementById('videoDropzone');
    const fileInput = document.getElementById('videoFileInput');
    const previewContainer = document.getElementById('videoPreviewContainer');
    const previewVideo = document.getElementById('previewVideo');
    const previewMeta = document.getElementById('previewMeta');
    const durationAlert = document.getElementById('durationAlert');
    const titleInput = document.getElementById('videoTitle');
    const uploadForm = document.getElementById('videoUploadForm');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressContainer = document.getElementById('uploadProgressContainer');
    const submitBtn = document.getElementById('submitUploadBtn');

    let currentFile = null;

    if (!dropzone || !fileInput) return;

    // Click to select
    dropzone.addEventListener('click', (e) => {
        // Prevent infinite loop if clicking on input itself
        if (e.target !== fileInput) {
            fileInput.click();
        }
    });

    // Drag and drop events
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files && files.length > 0) {
            setFile(files[0]);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files && fileInput.files.length > 0) {
            setFile(fileInput.files[0]);
        }
    });

    function setFile(file) {
        if (!file) return;

        // Verify it is a video
        const ext = file.name.split('.').pop().toLowerCase();
        if (!file.type.startsWith('video/') && !['mp4', 'mov', 'webm'].includes(ext)) {
            alert('Please select a valid video file (MP4, MOV, or WebM).');
            return;
        }

        currentFile = file;

        // Sync with fileInput using DataTransfer so normal form submission has it
        try {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
        } catch (err) {
            // DataTransfer not supported in older browsers, currentFile will be used directly
        }

        handleFileSelect(file);
    }

    function handleFileSelect(file) {
        // Auto-fill title if empty
        if (titleInput && !titleInput.value) {
            const cleanName = file.name.replace(/\.[^/.]+$/, "").replace(/[_-]/g, " ");
            titleInput.value = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
        }

        // Create blob preview
        const blobUrl = URL.createObjectURL(file);
        if (previewVideo) {
            previewVideo.src = blobUrl;
            previewVideo.load();
        }

        if (previewContainer) {
            previewContainer.classList.remove('d-none');
            dropzone.classList.add('d-none');
        }

        // Detect video duration and resolution via HTML5 video element
        const tempVideo = document.createElement('video');
        tempVideo.preload = 'metadata';
        tempVideo.src = blobUrl;
        tempVideo.onloadedmetadata = function() {
            window.URL.revokeObjectURL(tempVideo.src);
            const duration = tempVideo.duration;
            const width = tempVideo.videoWidth;
            const height = tempVideo.videoHeight;
            const sizeMb = (file.size / (1024 * 1024)).toFixed(1);

            if (previewMeta) {
                previewMeta.innerHTML = `
                    <div class="row g-2 text-muted small mt-2">
                        <div class="col-4"><strong>Duration:</strong> ${duration.toFixed(1)}s</div>
                        <div class="col-4"><strong>Resolution:</strong> ${width}x${height}</div>
                        <div class="col-4"><strong>Size:</strong> ${sizeMb} MB</div>
                    </div>
                `;
            }

            // Client-side duration validation alert (5-60s)
            if (durationAlert) {
                if (duration < 5 || duration > 60) {
                    durationAlert.classList.remove('d-none');
                    durationAlert.innerHTML = `
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Notice: Detected duration is <strong>${duration.toFixed(1)}s</strong>. Accepted duration for YouTube Shorts & Reels is <strong>5 to 60 seconds</strong>.
                    `;
                } else {
                    durationAlert.classList.add('d-none');
                }
            }
        };
    }

    // Reset button
    const changeVideoBtn = document.getElementById('btnChangeVideo');
    if (changeVideoBtn) {
        changeVideoBtn.addEventListener('click', () => {
            currentFile = null;
            fileInput.value = '';
            if (previewVideo) previewVideo.src = '';
            if (previewContainer) previewContainer.classList.add('d-none');
            dropzone.classList.remove('d-none');
            if (durationAlert) durationAlert.classList.add('d-none');
        });
    }

    // Schedule datetime toggle
    const publishActionRadios = document.querySelectorAll('input[name="publish_action"]');
    const schedulePickerContainer = document.getElementById('schedulePickerContainer');
    if (publishActionRadios && schedulePickerContainer) {
        publishActionRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === 'schedule' && radio.checked) {
                    schedulePickerContainer.classList.remove('d-none');
                } else {
                    schedulePickerContainer.classList.add('d-none');
                }
            });
        });
    }

    // AJAX upload with progress bar
    if (uploadForm && progressContainer && progressBar) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate that a file is present
            if (!currentFile && (!fileInput.files || fileInput.files.length === 0)) {
                alert('Please select or drag & drop a video file first.');
                return;
            }

            const formData = new FormData(uploadForm);

            // Ensure the video file is attached
            if (currentFile) {
                formData.set('video_file', currentFile, currentFile.name);
            }

            progressContainer.classList.remove('d-none');
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading Video...';
            }

            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                }
            });

            xhr.addEventListener('load', function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success && res.redirect) {
                            window.location.href = res.redirect;
                            return;
                        }
                    } catch (e) {}
                    window.location.href = uploadForm.getAttribute('action').replace('/upload', '/videos');
                } else {
                    let errMsg = 'Upload failed. Please check file format and duration.';
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.message) errMsg = res.message;
                    } catch (e) {}

                    alert('Upload Error: ' + errMsg);
                    progressContainer.classList.add('d-none');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Upload & Process Video';
                    }
                }
            });

            xhr.addEventListener('error', function() {
                alert('A network error occurred while uploading.');
                progressContainer.classList.add('d-none');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Upload & Process Video';
                }
            });

            xhr.open('POST', uploadForm.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });
    }
});
