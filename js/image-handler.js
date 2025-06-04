// Image handling functionality
document.addEventListener('DOMContentLoaded', function() {
  const productImages = document.getElementById('productImages');
  const imagePreview = document.getElementById('imagePreview');
  const captureBtn = document.getElementById('captureBtn');
  const cropBtn = document.getElementById('cropBtn');
  const cropperModal = document.getElementById('cropperModal');
  const cropperImage = document.getElementById('cropperImage');
  const applyCropBtn = document.getElementById('applyCrop');
  const cancelCropBtn = document.getElementById('cancelCrop');
  
  let cropper;
  let currentImageIndex = -1;
  
  // Handle file selection
  productImages.addEventListener('change', function(e) {
    const files = e.target.files;
    
    if (files.length > 0) {
      Array.from(files).forEach(file => {
        if (file.type.match('image.*')) {
          const reader = new FileReader();
          
          reader.onload = function(e) {
            addImageToPreview(e.target.result);
          };
          
          reader.readAsDataURL(file);
        }
      });
    }
  });
  
  // Add image to preview
  function addImageToPreview(src) {
    const container = document.createElement('div');
    container.className = 'preview-item';
    
    const img = document.createElement('img');
    img.src = src;
    img.alt = 'Product image';
    img.style.cursor = 'pointer';
    
    // Add click event to open image in cropper
    img.addEventListener('click', function(e) {
      e.preventDefault(); // Prevent default behavior
      e.stopPropagation(); // Stop event propagation
      
      // Find the index of the clicked image in the preview
      const images = imagePreview.querySelectorAll('.preview-item img');
      images.forEach((previewImg, index) => {
        if (previewImg === img) {
          window.currentImageIndex = index; // Store the index globally
        }
      });
      
      // Show image in cropper
      const cropperModal = document.getElementById('cropperModal');
      const cropperImage = document.getElementById('cropperImage');
      
      if (cropperModal && cropperImage) {
        cropperImage.src = src;
        cropperModal.style.display = 'block';
        
        // Initialize cropper
        if (window.cropper) {
          window.cropper.destroy();
        }
        
        window.cropper = new Cropper(cropperImage, {
          aspectRatio: 1,
          viewMode: 1,
          autoCropArea: 0.8,
          responsive: true
        });
      }
    });
    
    const removeBtn = document.createElement('div');
    removeBtn.className = 'remove-image';
    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
    removeBtn.addEventListener('click', function(e) {
      e.preventDefault(); // Prevent default behavior
      e.stopPropagation(); // Stop event propagation
      container.remove();
      // Update currentImageIndex if the removed image was before the current one
      if (window.currentImageIndex !== -1) {
        const remainingImages = imagePreview.querySelectorAll('.preview-item img');
        if (window.currentImageIndex >= remainingImages.length) {
            window.currentImageIndex = -1; // The current image was removed
        } else {
             // Re-find the index of the image that was previously at window.currentImageIndex
             // This is complex, simpler to just reset or re-evaluate index on crop button click
             // For now, we'll just decrement if the removed item was before the current index
             // A more robust solution might involve data attributes or a list of objects
             if (Array.from(imagePreview.children).indexOf(container) < window.currentImageIndex) {
                 window.currentImageIndex--;
             }
        }
      }
    });
    
    container.appendChild(img);
    container.appendChild(removeBtn);
    imagePreview.appendChild(container);
  }
  
  // Handle camera capture
  captureBtn.addEventListener('click', function() {
    // Check if device has camera access
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      const videoModal = document.createElement('div');
      videoModal.className = 'modal';
      videoModal.style.display = 'block';
      
      const modalContent = document.createElement('div');
      modalContent.className = 'modal-content';
      
      const closeBtn = document.createElement('span');
      closeBtn.className = 'close';
      closeBtn.innerHTML = '&times;';
      
      const title = document.createElement('h2');
      title.className = 'text-lg font-bold mb-3';
      title.textContent = 'Capture Image';
      
      const video = document.createElement('video');
      video.className = 'w-full h-64 bg-black rounded-lg';
      video.autoplay = true;
      
      const captureContainer = document.createElement('div');
      captureContainer.className = 'flex justify-center mt-3';
      
      const captureImageBtn = document.createElement('button');
      captureImageBtn.className = 'btn-primary';
      captureImageBtn.innerHTML = '<i class="fas fa-camera mr-2"></i> Capture';
      
      captureContainer.appendChild(captureImageBtn);
      modalContent.appendChild(closeBtn);
      modalContent.appendChild(title);
      modalContent.appendChild(video);
      modalContent.appendChild(captureContainer);
      videoModal.appendChild(modalContent);
      document.body.appendChild(videoModal);
      
      // Get camera stream
      navigator.mediaDevices.getUserMedia({ video: true })
        .then(function(stream) {
          video.srcObject = stream;
          
          // Capture image
          captureImageBtn.addEventListener('click', function() {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            
            const imageDataUrl = canvas.toDataURL('image/jpeg');
            addImageToPreview(imageDataUrl);
            
            // Stop camera stream and close modal
            stream.getTracks().forEach(track => track.stop());
            videoModal.remove();
          });
          
          // Close modal
          closeBtn.addEventListener('click', function() {
            stream.getTracks().forEach(track => track.stop());
            videoModal.remove();
          });
          
          window.addEventListener('click', function(event) {
            if (event.target === videoModal) {
              stream.getTracks().forEach(track => track.stop());
              videoModal.remove();
            }
          });
        })
        .catch(function(error) {
          alert('Error accessing camera: ' + error.message);
          videoModal.remove();
        });
    } else {
      alert('Your device does not support camera access');
    }
  });
  
  // Handle crop button click
  cropBtn.addEventListener('click', function() {
    const images = imagePreview.querySelectorAll('.preview-item img');
    
    if (images.length === 0) {
      alert('Please add images first');
      return;
    }
    
    // If no image was previously clicked, default to the first one
    if (window.currentImageIndex === undefined || window.currentImageIndex === -1 || window.currentImageIndex >= images.length) {
       window.currentImageIndex = 0;
    }

    // Show the currently selected image in cropper
    cropperImage.src = images[window.currentImageIndex].src;
    cropperModal.style.display = 'block';
    
    // Initialize cropper
    if (window.cropper) {
      window.cropper.destroy();
    }
    
    window.cropper = new Cropper(cropperImage, {
      aspectRatio: 1,
      viewMode: 1,
      autoCropArea: 0.8,
      responsive: true
    });
  });
  
  // Apply crop
  applyCropBtn.addEventListener('click', function() {
    if (!window.cropper || window.currentImageIndex === -1) return;
    
    const croppedCanvas = window.cropper.getCroppedCanvas();
    if (croppedCanvas) {
      const croppedImageDataUrl = croppedCanvas.toDataURL('image/jpeg');
      const images = imagePreview.querySelectorAll('.preview-item img');
      
      if (window.currentImageIndex >= 0 && window.currentImageIndex < images.length) {
        images[window.currentImageIndex].src = croppedImageDataUrl; // Update the correct image
      }
      
      window.cropper.destroy();
      window.cropper = null;
      cropperModal.style.display = 'none';
      window.currentImageIndex = -1; // Reset index after cropping
    }
  });
  
  // Cancel crop
  cancelCropBtn.addEventListener('click', function() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
    cropperModal.style.display = 'none';
  });
  
  // Close modal when clicking on X
  document.querySelector('#cropperModal .close').addEventListener('click', function() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
    cropperModal.style.display = 'none';
  });
  
  // Close modal when clicking outside
  window.addEventListener('click', function(event) {
    if (event.target === cropperModal) {
      if (cropper) {
        cropper.destroy();
        cropper = null;
      }
      cropperModal.style.display = 'none';
    }
  });
}); 