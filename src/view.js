/**
 * Frontend JavaScript for Elementor Template Kit Manager
 */

(function() {
	'use strict';

	if (typeof etkm === 'undefined') {
		return;
	}

	const uploadArea = document.getElementById('etkm-upload-area');
	const fileInput = document.getElementById('etkm-file-input');
	const selectFileBtn = document.getElementById('etkm-select-file');
	const kitsList = document.getElementById('etkm-kits-list');
	let currentView = 'gallery';
	let selectedKits = new Set();
	let mediaFrame;

	if (!uploadArea || !fileInput || !selectFileBtn || !kitsList) {
		return;
	}

	// Initialize
	loadKits();

	// View toggle buttons
	document.querySelectorAll('.etkm-view-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			const view = this.dataset.view;
			if (view !== currentView) {
				currentView = view;
				document.querySelectorAll('.etkm-view-btn').forEach(b => b.classList.remove('active'));
				this.classList.add('active');
				toggleView(view);
			}
		});
	});

	function toggleView(view) {
		if (view === 'gallery') {
			kitsList.classList.remove('etkm-list-view');
			kitsList.classList.add('etkm-gallery-view');
		} else {
			kitsList.classList.remove('etkm-gallery-view');
			kitsList.classList.add('etkm-list-view');
		}
		loadKits();
	}

	// Select file button
	selectFileBtn.addEventListener('click', function() {
		fileInput.click();
	});

	// File input change
	fileInput.addEventListener('change', function(e) {
		if (e.target.files.length > 0) {
			handleFile(e.target.files[0]);
		}
	});

	// Drag and drop
	uploadArea.addEventListener('dragover', function(e) {
		e.preventDefault();
		e.stopPropagation();
		uploadArea.classList.add('drag-over');
	});

	uploadArea.addEventListener('dragleave', function(e) {
		e.preventDefault();
		e.stopPropagation();
		uploadArea.classList.remove('drag-over');
	});

	uploadArea.addEventListener('drop', function(e) {
		e.preventDefault();
		e.stopPropagation();
		uploadArea.classList.remove('drag-over');

		const files = e.dataTransfer.files;
		if (files.length > 0) {
			handleFile(files[0]);
		}
	});

	function handleFile(file) {
		// Validate file type
		if (!file.name.endsWith('.zip')) {
			showNotification(etkm.strings.invalidFile, 'error');
			return;
		}

		// Validate file size
		if (file.size > etkm.maxFileSize) {
			showNotification(etkm.strings.fileTooLarge, 'error');
			return;
		}

		uploadFile(file);
	}

	function uploadFile(file) {
		const formData = new FormData();
		formData.append('action', 'etkm_upload_kit');
		formData.append('nonce', etkm.nonce);
		formData.append('file', file);

		// Show progress
		const uploadContent = uploadArea.querySelector('.etkm-upload-content');
		const uploadProgress = uploadArea.querySelector('.etkm-upload-progress');
		uploadContent.style.display = 'none';
		uploadProgress.style.display = 'block';

		const xhr = new XMLHttpRequest();

		xhr.upload.addEventListener('progress', function(e) {
			if (e.lengthComputable) {
				const percentComplete = (e.loaded / e.total) * 100;
				updateProgress(percentComplete);
			}
		});

		xhr.addEventListener('load', function() {
			if (xhr.status === 200) {
				try {
					const response = JSON.parse(xhr.responseText);
					if (response.success) {
						showNotification(response.data.message, 'success');
						loadKits();
						resetUploadArea();
					} else {
						showNotification(response.data.message || etkm.strings.uploadError, 'error');
						resetUploadArea();
					}
				} catch (error) {
					showNotification(etkm.strings.uploadError, 'error');
					resetUploadArea();
				}
			} else {
				showNotification(etkm.strings.uploadError, 'error');
				resetUploadArea();
			}
		});

		xhr.addEventListener('error', function() {
			showNotification(etkm.strings.uploadError, 'error');
			resetUploadArea();
		});

		xhr.open('POST', etkm.ajaxUrl);
		xhr.send(formData);
	}

	function updateProgress(percent) {
		const progressFill = document.querySelector('.etkm-progress-fill');
		const progressText = document.querySelector('.etkm-progress-text');
		if (progressFill && progressText) {
			progressFill.style.width = percent + '%';
			progressText.textContent = Math.round(percent) + '%';
		}
	}

	function resetUploadArea() {
		const uploadContent = uploadArea.querySelector('.etkm-upload-content');
		const uploadProgress = uploadArea.querySelector('.etkm-upload-progress');
		setTimeout(function() {
			uploadContent.style.display = 'block';
			uploadProgress.style.display = 'none';
			updateProgress(0);
			fileInput.value = '';
		}, 1000);
	}

	function loadKits() {
		kitsList.innerHTML = '<div class="etkm-loading"><span class="spinner is-active"></span><p>Loading template kits...</p></div>';

		fetch(etkm.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'etkm_get_kits',
				nonce: etkm.nonce
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				renderKits(data.data.kits);
			} else {
				kitsList.innerHTML = '<p>Failed to load template kits.</p>';
			}
		})
		.catch(error => {
			kitsList.innerHTML = '<p>Failed to load template kits.</p>';
		});
	}

	function renderKits(kits) {
		if (kits.length === 0) {
			kitsList.innerHTML = `
				<div class="etkm-empty-state">
					<span class="dashicons dashicons-download"></span>
					<h3>No Template Kits Yet</h3>
					<p>Upload your first Elementor template kit to get started.</p>
				</div>
			`;
			return;
		}

		let html = '';
		
		if (currentView === 'gallery') {
			kits.forEach(function(kit) {
				const isSelected = selectedKits.has(kit.name);
				const previewHtml = kit.preview 
					? `<img src="${escapeHtml(kit.preview)}" alt="${escapeHtml(kit.display_name)}" loading="lazy">`
					: `<span class="dashicons dashicons-media-document etkm-placeholder-icon"></span>`;
				
				html += `
					<div class="etkm-gallery-card ${isSelected ? 'selected' : ''}" data-filename="${escapeHtml(kit.name)}">
						<div class="etkm-card-preview">
							${previewHtml}
							<button type="button" class="etkm-add-image-btn" data-filename="${escapeHtml(kit.name)}">
								<span class="dashicons dashicons-format-image"></span>
								${kit.preview ? 'Change Image' : 'Add Image'}
							</button>
							<div class="etkm-card-actions">
								<button type="button" class="button button-primary etkm-import-btn" data-filename="${escapeHtml(kit.name)}">
									<span class="dashicons dashicons-download"></span> Import
								</button>
								<button type="button" class="button button-secondary etkm-delete-btn" data-filename="${escapeHtml(kit.name)}">
									<span class="dashicons dashicons-trash"></span> Delete
								</button>
							</div>
						</div>
						<div class="etkm-card-info">
							<h3>${escapeHtml(kit.display_name)}</h3>
							<div class="etkm-card-meta">
								<span><span class="dashicons dashicons-calendar-alt"></span>${escapeHtml(kit.date)}</span>
								<span><span class="dashicons dashicons-media-archive"></span>${escapeHtml(kit.size)}</span>
							</div>
						</div>
					</div>
				`;
			});
		} else {
			kits.forEach(function(kit) {
				html += `
					<div class="etkm-kit-item" data-filename="${escapeHtml(kit.name)}">
						<div class="etkm-kit-info">
							<h3>${escapeHtml(kit.display_name)}</h3>
							<div class="etkm-kit-meta">
								<span><span class="dashicons dashicons-calendar-alt"></span>${escapeHtml(kit.date)}</span>
								<span><span class="dashicons dashicons-media-archive"></span>${escapeHtml(kit.size)}</span>
							</div>
						</div>
						<div class="etkm-kit-actions">
							<button type="button" class="button etkm-add-image-btn-list" data-filename="${escapeHtml(kit.name)}">
								<span class="dashicons dashicons-format-image"></span> ${kit.preview ? 'Change' : 'Add'} Image
							</button>
							<button type="button" class="button button-primary etkm-import-btn" data-filename="${escapeHtml(kit.name)}">
								<span class="dashicons dashicons-download"></span> Import
							</button>
							<button type="button" class="button etkm-delete-btn" data-filename="${escapeHtml(kit.name)}">
								<span class="dashicons dashicons-trash"></span> Delete
							</button>
						</div>
					</div>
				`;
			});
		}

		kitsList.innerHTML = html;

		// Attach event listeners
		if (currentView === 'gallery') {
			document.querySelectorAll('.etkm-gallery-card').forEach(function(card) {
				card.addEventListener('click', function(e) {
					if (!e.target.closest('.etkm-import-btn') && 
						!e.target.closest('.etkm-delete-btn') && 
						!e.target.closest('.etkm-add-image-btn')) {
						const filename = this.dataset.filename;
						if (selectedKits.has(filename)) {
							selectedKits.delete(filename);
							this.classList.remove('selected');
						} else {
							selectedKits.add(filename);
							this.classList.add('selected');
						}
					}
				});
			});
		}

		document.querySelectorAll('.etkm-import-btn').forEach(function(btn) {
			btn.addEventListener('click', handleImport);
		});

		document.querySelectorAll('.etkm-delete-btn').forEach(function(btn) {
			btn.addEventListener('click', handleDelete);
		});

		document.querySelectorAll('.etkm-add-image-btn').forEach(function(btn) {
			btn.addEventListener('click', handleAddImage);
		});

		document.querySelectorAll('.etkm-add-image-btn-list').forEach(function(btn) {
			btn.addEventListener('click', handleAddImage);
		});
	}

	function handleAddImage(e) {
		e.stopPropagation();
		const btn = e.currentTarget;
		const filename = btn.dataset.filename;

		// Create WordPress media frame if it doesn't exist
		if (!mediaFrame) {
			mediaFrame = wp.media({
				title: 'Select or Upload Image',
				button: {
					text: 'Use this image'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});
		}

		// Remove previous event listeners
		mediaFrame.off('select');

		// When an image is selected
		mediaFrame.on('select', function() {
			const attachment = mediaFrame.state().get('selection').first().toJSON();
			uploadImage(filename, attachment.id);
		});

		// Open the media frame
		mediaFrame.open();
	}

	function uploadImage(kitFilename, imageId) {
		fetch(etkm.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'etkm_upload_image',
				nonce: etkm.nonce,
				kit_filename: kitFilename,
				image_id: imageId
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				showNotification(data.data.message, 'success');
				loadKits();
			} else {
				showNotification(data.data.message || etkm.strings.imageUploadError, 'error');
			}
		})
		.catch(error => {
			showNotification(etkm.strings.imageUploadError, 'error');
		});
	}

	function handleImport(e) {
		e.stopPropagation();
		const btn = e.currentTarget;
		const filename = btn.dataset.filename;
		const originalText = btn.innerHTML;

		if (btn.disabled) {
			return;
		}

		btn.disabled = true;
		btn.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>Importing...';

		fetch(etkm.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'etkm_import_kit',
				nonce: etkm.nonce,
				filename: filename
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				let message = data.data.message;
				if (data.data.imported && data.data.imported.length > 0) {
					message += '<ul>';
					data.data.imported.forEach(function(template) {
						message += '<li>' + escapeHtml(template.title) + ' (' + escapeHtml(template.type) + ')</li>';
					});
					message += '</ul>';
				}
				showNotification(message, 'success');
			} else {
				showNotification(data.data.message || etkm.strings.importError, 'error');
			}
			btn.disabled = false;
			btn.innerHTML = originalText;
		})
		.catch(error => {
			showNotification(etkm.strings.importError, 'error');
			btn.disabled = false;
			btn.innerHTML = originalText;
		});
	}

	function handleDelete(e) {
		e.stopPropagation();
		const btn = e.currentTarget;
		const filename = btn.dataset.filename;

		if (!confirm(etkm.strings.deleteConfirm)) {
			return;
		}

		const originalText = btn.innerHTML;
		btn.disabled = true;
		btn.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span>';

		fetch(etkm.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				action: 'etkm_delete_kit',
				nonce: etkm.nonce,
				filename: filename
			})
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				showNotification(data.data.message, 'success');
				selectedKits.delete(filename);
				loadKits();
			} else {
				showNotification(data.data.message || 'Failed to delete template kit.', 'error');
				btn.disabled = false;
				btn.innerHTML = originalText;
			}
		})
		.catch(error => {
			showNotification('Failed to delete template kit.', 'error');
			btn.disabled = false;
			btn.innerHTML = originalText;
		});
	}

	function showNotification(message, type) {
		const notification = document.getElementById('etkm-notification');
		if (!notification) {
			return;
		}

		notification.className = 'etkm-notification ' + type;
		notification.innerHTML = '<p>' + message + '</p>';
		notification.style.display = 'block';

		setTimeout(function() {
			notification.style.display = 'none';
		}, 5000);
	}

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}
})();