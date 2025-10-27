(function () {
  function noop() {}

  document.addEventListener('prsCoverModal:ready', (event) => {
    const detail = event.detail || {};
    const stage = detail.stage;
    const placeholder = detail.placeholder || null;
    const previewImage = detail.previewImage || null;
    const fileInput = detail.fileInput || null;
    const cropArea = detail.cropArea || null;
    const onFiles = typeof detail.onFiles === 'function' ? detail.onFiles : noop;

    if (!stage || !fileInput) {
      return;
    }

    const preventDefaults = (e) => {
      e.preventDefault();
      e.stopPropagation();
    };

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((evtName) => {
      stage.addEventListener(evtName, preventDefaults);
    });

    ['dragenter', 'dragover'].forEach((evtName) => {
      stage.addEventListener(evtName, () => {
        stage.classList.add('drag-active');
        stage.classList.remove('error');
      });
    });

    ['dragleave', 'drop'].forEach((evtName) => {
      stage.addEventListener(evtName, () => {
        stage.classList.remove('drag-active');
      });
    });

    stage.addEventListener('drop', (eventDrop) => {
      const files = eventDrop.dataTransfer ? eventDrop.dataTransfer.files : null;
      if (files && files.length) {
        onFiles(files);
      }
    });

    const triggerFileInput = () => {
      fileInput.click();
    };

    stage.addEventListener('click', (eventClick) => {
      if (cropArea && cropArea.contains(eventClick.target)) {
        return;
      }
      triggerFileInput();
    });

    if (placeholder) {
      placeholder.addEventListener('click', triggerFileInput);
    }

    fileInput.addEventListener('change', (eventChange) => {
      const files = eventChange.target.files;
      if (files && files.length) {
        onFiles(files);
      }
      eventChange.target.value = '';
    });
  });
})();
