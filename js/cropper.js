// Camera Cropping Functionality
class CameraCropper {
  constructor() {
    this.isDragging = false;
    this.isResizing = false;
    this.currentHandle = null;
    this.startX = 0;
    this.startY = 0;
    this.startLeft = 0;
    this.startTop = 0;
    this.startWidth = 0;
    this.startHeight = 0;
    this.rotation = 0;
    this.originalImageData = null;
    
    this.init();
  }

  init() {
    this.cameraModal = document.getElementById('cameraModal');
    this.cameraStage = document.getElementById('cameraStage');
    this.cropStage = document.getElementById('cropStage');
    this.cameraFeed = document.getElementById('cameraFeed');
    this.captureCanvas = document.getElementById('captureCanvas');
    this.capturePreview = document.getElementById('capturePreview');
    this.cropOverlay = document.getElementById('cropOverlay');
    this.cropBox = document.getElementById('cropBox');
    
    // Camera controls
    this.cameraModeControls = document.getElementById('cameraModeControls');
    this.cropModeControls = document.getElementById('cropModeControls');
    this.captureBtn = document.getElementById('captureBtn');
    this.acceptCropBtn = document.getElementById('acceptCropBtn');
    this.retakeCaptureBtn = document.getElementById('retakeCaptureBtn');
    this.cropRotateBtn = document.getElementById('cropRotateBtn');
    this.cropResetBtn = document.getElementById('cropResetBtn');
    this.closeCameraBtn = document.getElementById('closeCameraBtn');
    this.switchCameraBtn = document.getElementById('switchCameraBtn');
    
    // Debug logging
    console.log('CameraCropper initialized with elements:', {
      cameraModal: !!this.cameraModal,
      cameraStage: !!this.cameraStage,
      cropStage: !!this.cropStage,
      cameraFeed: !!this.cameraFeed,
      captureCanvas: !!this.captureCanvas,
      capturePreview: !!this.capturePreview,
      cropOverlay: !!this.cropOverlay,
      cropBox: !!this.cropBox
    });
    
    this.bindEvents();
  }

  bindEvents() {
    // Camera mode events
    this.captureBtn?.addEventListener('click', () => this.captureImage());
    this.closeCameraBtn?.addEventListener('click', () => this.closeCamera());
    this.switchCameraBtn?.addEventListener('click', () => this.switchCamera());
    
    // Crop mode events
    this.acceptCropBtn?.addEventListener('click', () => this.acceptCrop());
    this.retakeCaptureBtn?.addEventListener('click', () => this.retakeCapture());
    this.cropRotateBtn?.addEventListener('click', () => this.rotateImage());
    this.cropResetBtn?.addEventListener('click', () => this.resetCrop());
    
    // Crop box events
    this.cropBox?.addEventListener('mousedown', (e) => this.startDrag(e));
    this.cropOverlay?.addEventListener('mousedown', (e) => this.startCrop(e));
    
    // Touch events for crop box
    this.cropBox?.addEventListener('touchstart', (e) => this.startDrag(e), { passive: false });
    this.cropOverlay?.addEventListener('touchstart', (e) => this.startCrop(e), { passive: false });
    
    // Handle events
    const handles = document.querySelectorAll('.crop-handle');
    handles.forEach(handle => {
      handle.addEventListener('mousedown', (e) => this.startResize(e));
      handle.addEventListener('touchstart', (e) => this.startResize(e), { passive: false });
    });
    
    // Global mouse events
    document.addEventListener('mousemove', (e) => this.onMouseMove(e));
    document.addEventListener('mouseup', () => this.stopInteraction());
    
    // Touch events for mobile
    document.addEventListener('touchmove', (e) => this.onTouchMove(e), { passive: false });
    document.addEventListener('touchend', () => this.stopInteraction());
  }

  captureImage() {
    if (!this.captureCanvas || !this.cameraFeed) return;

    // Set canvas dimensions to match video
    this.captureCanvas.width = this.cameraFeed.videoWidth;
    this.captureCanvas.height = this.cameraFeed.videoHeight;

    // Draw video frame to canvas
    const context = this.captureCanvas.getContext('2d');
    context.drawImage(this.cameraFeed, 0, 0, this.captureCanvas.width, this.captureCanvas.height);

    // Store original image data
    this.originalImageData = this.captureCanvas.toDataURL('image/jpeg');
    
    // Show crop stage
    this.showCropStage();
  }

  showCropStage() {
    if (!this.cameraStage || !this.cropStage || !this.capturePreview) return;

    // Set the preview image
    this.capturePreview.src = this.originalImageData;
    
    // Hide camera stage, show crop stage
    this.cameraStage.classList.add('hidden');
    this.cropStage.classList.remove('hidden');
    
    // Switch controls
    this.cameraModeControls.classList.add('hidden');
    this.cropModeControls.classList.remove('hidden');
    
    // Initialize crop box
    this.initializeCropBox();
    
    // Add instructions
    this.addCropInstructions();
  }

  initializeCropBox() {
    if (!this.cropBox || !this.capturePreview) return;

    // Wait for image to load
    this.capturePreview.onload = () => {
      const container = this.cropOverlay;
      const image = this.capturePreview;
      
      // Calculate initial crop box size (center 80% of image)
      const containerRect = container.getBoundingClientRect();
      const imageRect = image.getBoundingClientRect();
      
      const cropWidth = imageRect.width * 0.8;
      const cropHeight = imageRect.height * 0.8;
      const cropLeft = (containerRect.width - cropWidth) / 2;
      const cropTop = (containerRect.height - cropHeight) / 2;
      
      // Set initial crop box position and size
      this.cropBox.style.left = cropLeft + 'px';
      this.cropBox.style.top = cropTop + 'px';
      this.cropBox.style.width = cropWidth + 'px';
      this.cropBox.style.height = cropHeight + 'px';
    };
  }

  addCropInstructions() {
    const instructions = document.createElement('div');
    instructions.className = 'crop-instructions';
    instructions.innerHTML = 'Drag to move • Drag corners to resize • Click outside to create new crop';
    this.cropStage.appendChild(instructions);
    
    // Remove instructions after 3 seconds
    setTimeout(() => {
      if (instructions.parentNode) {
        instructions.remove();
      }
    }, 3000);
  }

  startDrag(e) {
    if (e.target.classList.contains('crop-handle')) return;
    
    this.isDragging = true;
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    this.startX = clientX;
    this.startY = clientY;
    this.startLeft = parseInt(this.cropBox.style.left) || 0;
    this.startTop = parseInt(this.cropBox.style.top) || 0;
    
    e.preventDefault();
  }

  startResize(e) {
    this.isResizing = true;
    this.currentHandle = e.target;
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    this.startX = clientX;
    this.startY = clientY;
    this.startLeft = parseInt(this.cropBox.style.left) || 0;
    this.startTop = parseInt(this.cropBox.style.top) || 0;
    this.startWidth = parseInt(this.cropBox.style.width) || 0;
    this.startHeight = parseInt(this.cropBox.style.height) || 0;
    
    e.preventDefault();
    e.stopPropagation();
  }

  startCrop(e) {
    if (e.target === this.cropOverlay) {
      // Create new crop box at click/touch position
      const rect = this.cropOverlay.getBoundingClientRect();
      const clientX = e.touches ? e.touches[0].clientX : e.clientX;
      const clientY = e.touches ? e.touches[0].clientY : e.clientY;
      const x = clientX - rect.left;
      const y = clientY - rect.top;
      
      const size = 100; // Default crop size
      this.cropBox.style.left = (x - size/2) + 'px';
      this.cropBox.style.top = (y - size/2) + 'px';
      this.cropBox.style.width = size + 'px';
      this.cropBox.style.height = size + 'px';
    }
  }

  onMouseMove(e) {
    if (this.isDragging) {
      this.handleDrag(e);
    } else if (this.isResizing) {
      this.handleResize(e);
    }
  }

  onTouchMove(e) {
    if (this.isDragging) {
      this.handleDrag(e);
    } else if (this.isResizing) {
      this.handleResize(e);
    }
    e.preventDefault();
  }

  handleDrag(e) {
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    const deltaX = clientX - this.startX;
    const deltaY = clientY - this.startY;
    
    const newLeft = this.startLeft + deltaX;
    const newTop = this.startTop + deltaY;
    
    // Constrain to container bounds
    const containerRect = this.cropOverlay.getBoundingClientRect();
    const boxRect = this.cropBox.getBoundingClientRect();
    
    const maxLeft = containerRect.width - boxRect.width;
    const maxTop = containerRect.height - boxRect.height;
    
    this.cropBox.style.left = Math.max(0, Math.min(newLeft, maxLeft)) + 'px';
    this.cropBox.style.top = Math.max(0, Math.min(newTop, maxTop)) + 'px';
  }

  handleResize(e) {
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    const deltaX = clientX - this.startX;
    const deltaY = clientY - this.startY;
    
    let newLeft = this.startLeft;
    let newTop = this.startTop;
    let newWidth = this.startWidth;
    let newHeight = this.startHeight;
    
    const handleClass = this.currentHandle.className;
    
    if (handleClass.includes('nw')) {
      newLeft = this.startLeft + deltaX;
      newTop = this.startTop + deltaY;
      newWidth = this.startWidth - deltaX;
      newHeight = this.startHeight - deltaY;
    } else if (handleClass.includes('ne')) {
      newTop = this.startTop + deltaY;
      newWidth = this.startWidth + deltaX;
      newHeight = this.startHeight - deltaY;
    } else if (handleClass.includes('sw')) {
      newLeft = this.startLeft + deltaX;
      newWidth = this.startWidth - deltaX;
      newHeight = this.startHeight + deltaY;
    } else if (handleClass.includes('se')) {
      newWidth = this.startWidth + deltaX;
      newHeight = this.startHeight + deltaY;
    } else if (handleClass.includes('n')) {
      newTop = this.startTop + deltaY;
      newHeight = this.startHeight - deltaY;
    } else if (handleClass.includes('s')) {
      newHeight = this.startHeight + deltaY;
    } else if (handleClass.includes('w')) {
      newLeft = this.startLeft + deltaX;
      newWidth = this.startWidth - deltaX;
    } else if (handleClass.includes('e')) {
      newWidth = this.startWidth + deltaX;
    }
    
    // Apply minimum size constraints
    const minSize = 50;
    if (newWidth >= minSize && newHeight >= minSize) {
      this.cropBox.style.left = newLeft + 'px';
      this.cropBox.style.top = newTop + 'px';
      this.cropBox.style.width = newWidth + 'px';
      this.cropBox.style.height = newHeight + 'px';
    }
  }

  stopInteraction() {
    this.isDragging = false;
    this.isResizing = false;
    this.currentHandle = null;
  }

  rotateImage() {
    this.rotation = (this.rotation + 90) % 360;
    this.capturePreview.style.transform = `rotate(${this.rotation}deg)`;
  }

  resetCrop() {
    this.rotation = 0;
    this.capturePreview.style.transform = '';
    this.initializeCropBox();
  }

  acceptCrop() {
    if (!this.originalImageData) return;

    // Create a new canvas for the cropped image
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    // Load the original image
    const img = new Image();
    img.onload = () => {
      // Calculate crop dimensions
      const cropRect = this.cropBox.getBoundingClientRect();
      const imageRect = this.capturePreview.getBoundingClientRect();
      
      // Calculate scale factors
      const scaleX = img.width / imageRect.width;
      const scaleY = img.height / imageRect.height;
      
      // Calculate crop coordinates in image space
      const cropX = (cropRect.left - imageRect.left) * scaleX;
      const cropY = (cropRect.top - imageRect.top) * scaleY;
      const cropWidth = cropRect.width * scaleX;
      const cropHeight = cropRect.height * scaleY;
      
      // Set canvas size to crop size
      canvas.width = cropWidth;
      canvas.height = cropHeight;
      
      // Apply rotation if needed
      if (this.rotation !== 0) {
        ctx.save();
        ctx.translate(canvas.width/2, canvas.height/2);
        ctx.rotate((this.rotation * Math.PI) / 180);
        ctx.drawImage(img, -cropWidth/2, -cropHeight/2, cropWidth, cropHeight);
        ctx.restore();
      } else {
        // Draw cropped portion
        ctx.drawImage(img, cropX, cropY, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);
      }
      
      // Get the cropped image data
      const croppedImageData = canvas.toDataURL('image/jpeg', 0.9);
      
      // Store the cropped image
      this.croppedImageData = croppedImageData;
      
      // Close camera and return to form
      this.closeCamera();
      
      // Update the captured image field in the form
      const capturedImageField = document.getElementById('capturedImage');
      if (capturedImageField) {
        capturedImageField.value = croppedImageData;
      }
      
      // Add to image preview
      const imagePreview = document.getElementById('imagePreview');
      if (imagePreview) {
        const imgContainer = document.createElement('div');
        imgContainer.className = 'relative';

        const img = document.createElement('img');
        img.src = croppedImageData;
        img.className = 'w-16 h-16 object-cover rounded-md border border-gray-200';

        const removeBtn = document.createElement('button');
        removeBtn.className = 'absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-sm hover:bg-red-600';
        removeBtn.innerHTML = '<i class="fas fa-times text-xs"></i>';
        removeBtn.onclick = (e) => {
          e.preventDefault();
          imgContainer.remove();
          capturedImageField.value = '';
        };

        const captureLabel = document.createElement('div');
        captureLabel.className = 'absolute -bottom-1 -right-1 bg-purple-500 text-white rounded-full text-xs px-1 flex items-center justify-center shadow-sm';
        captureLabel.innerHTML = '<i class="fas fa-camera text-xs"></i>';

        imgContainer.appendChild(img);
        imgContainer.appendChild(removeBtn);
        imgContainer.appendChild(captureLabel);
        imagePreview.appendChild(imgContainer);
      }
      
      // Show success message
      if (typeof showToast === 'function') {
        showToast('Image captured and cropped successfully!', 'success');
      }
    };
    
    img.src = this.originalImageData;
  }

  retakeCapture() {
    // Reset rotation
    this.rotation = 0;
    this.capturePreview.style.transform = '';
    
    // Show camera stage again
    this.cropStage.classList.add('hidden');
    this.cameraStage.classList.remove('hidden');
    
    // Switch controls back
    this.cropModeControls.classList.add('hidden');
    this.cameraModeControls.classList.remove('hidden');
  }

  closeCamera() {
    if (this.cameraModal) {
      this.cameraModal.classList.add('hidden');
    }
    
    // Stop camera stream
    if (window.cameraStream) {
      window.cameraStream.getTracks().forEach(track => track.stop());
      window.cameraStream = null;
    }
    
    // Reset states
    this.isDragging = false;
    this.isResizing = false;
    this.rotation = 0;
    this.originalImageData = null;
    this.croppedImageData = null;
  }

  switchCamera() {
    // Toggle between front and back camera
    if (window.currentFacingMode === 'user') {
      window.currentFacingMode = 'environment';
    } else {
      window.currentFacingMode = 'user';
    }
    
    // Restart camera with new facing mode
    this.restartCamera();
  }

  restartCamera() {
    if (window.cameraStream) {
      window.cameraStream.getTracks().forEach(track => track.stop());
    }
    
    navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: window.currentFacingMode,
        width: { ideal: 1280 },
        height: { ideal: 720 }
      },
      audio: false
    }).then(stream => {
      window.cameraStream = stream;
      this.cameraFeed.srcObject = stream;
    }).catch(error => {
      console.error('Error switching camera:', error);
      if (typeof showToast === 'function') {
        showToast('Error switching camera', 'error');
      }
    });
  }
}

// Initialize camera cropper when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  // Initialize camera cropper
  window.cameraCropper = new CameraCropper();
  
  // Set initial camera facing mode
  window.currentFacingMode = 'environment';
  
  // Trigger any pending camera button initializations
  if (typeof window.initializeCameraButtons === 'function') {
    window.initializeCameraButtons();
  }
  
  console.log('CameraCropper initialized successfully');
});

// Add openCamera method to CameraCropper class
CameraCropper.prototype.openCamera = function() {
  if (!this.cameraModal) return;

  this.cameraModal.classList.remove('hidden');
  
  // Show camera stage
  this.cameraStage.classList.remove('hidden');
  this.cropStage.classList.add('hidden');
  
  // Show camera controls
  this.cameraModeControls.classList.remove('hidden');
  this.cropModeControls.classList.add('hidden');
  
  // Start camera
  navigator.mediaDevices.getUserMedia({
    video: {
      facingMode: window.currentFacingMode || 'environment',
      width: { ideal: 1280 },
      height: { ideal: 720 }
    },
    audio: false
  }).then(stream => {
    window.cameraStream = stream;
    this.cameraFeed.srcObject = stream;
  }).catch(error => {
    console.error('Error accessing camera:', error);
    if (typeof showToast === 'function') {
      showToast('Error accessing camera: ' + error.message, 'error');
    }
    this.closeCamera();
  });
};
