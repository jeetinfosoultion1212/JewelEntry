# Camera Capture Feature

## Overview
This feature adds camera capture functionality with cropping capabilities to the jewelry management system. Users can now capture images directly from their device camera and crop them before adding to products.

## Features

### Camera Capture
- **Camera Access**: Uses device camera (front/back) for image capture
- **Switch Camera**: Toggle between front and back cameras
- **Real-time Preview**: Live camera feed with controls
- **Capture Button**: Take photos with a single click

### Image Cropping
- **Interactive Crop Box**: Drag to move, resize corners to adjust crop area
- **Rotation**: Rotate captured images by 90 degrees
- **Reset Function**: Reset crop and rotation to original state
- **Accept/Cancel**: Accept cropped image or retake photo

### Integration
- **Form Integration**: Captured images are automatically added to product form
- **Image Preview**: Shows captured images with camera indicator
- **File Upload**: Saves captured images as files on server
- **Database Storage**: Stores image references in database

## Technical Implementation

### Frontend Components

#### HTML Structure
```html
<!-- Camera Modal -->
<div id="cameraModal" class="camera-modal hidden">
  <div class="camera-container">
    <!-- Camera Stage -->
    <div id="cameraStage" class="camera-stage">
      <video id="cameraFeed" class="camera-feed" autoplay playsinline></video>
      <canvas id="captureCanvas" class="hidden"></canvas>
      
      <!-- Camera Controls -->
      <div id="cameraModeControls" class="camera-controls">
        <button id="switchCameraBtn" class="camera-button">Switch Camera</button>
        <button id="captureBtn" class="camera-button">Capture</button>
        <button id="closeCameraBtn" class="camera-button cancel">Close</button>
      </div>
    </div>
    
    <!-- Crop Stage -->
    <div id="cropStage" class="crop-stage hidden">
      <div class="crop-container">
        <img id="capturePreview" class="capture-preview" />
        <div id="cropOverlay" class="crop-overlay">
          <div id="cropBox" class="crop-box">
            <!-- Crop handles -->
          </div>
        </div>
      </div>
      
      <!-- Crop Controls -->
      <div id="cropModeControls" class="crop-controls">
        <button id="cropRotateBtn" class="crop-button">Rotate</button>
        <button id="cropResetBtn" class="crop-button">Reset</button>
        <button id="retakeCaptureBtn" class="crop-button">Retake</button>
        <button id="acceptCropBtn" class="crop-button accept">Accept</button>
      </div>
    </div>
  </div>
</div>
```

#### CSS Classes
- `.camera-modal`: Full-screen modal overlay
- `.camera-container`: Container for camera interface
- `.camera-feed`: Video element for camera stream
- `.crop-box`: Interactive crop selection area
- `.crop-handle`: Resize handles for crop box
- `.camera-button`: Styled camera control buttons

#### JavaScript Functions
- `openCamera()`: Initialize camera stream
- `captureImage()`: Capture current frame to canvas
- `showCropStage()`: Switch to cropping interface
- `acceptCrop()`: Process and save cropped image
- `rotateImage()`: Rotate image by 90 degrees
- `switchCamera()`: Toggle between front/back cameras

### Backend Processing

#### PHP Image Handling
```php
// Handle captured image if any
if (isset($_POST['capturedImage']) && !empty($_POST['capturedImage'])) {
    $uploadDir = 'uploads/jewelry/';
    
    // Decode base64 image data
    $imageData = $_POST['capturedImage'];
    $imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
    $imageData = base64_decode($imageData);
    
    if ($imageData !== false) {
        // Generate unique filename
        $newFileName = $product_id . '_' . time() . '_captured.jpg';
        $targetFilePath = $uploadDir . $newFileName;
        
        // Save the image file
        if (file_put_contents($targetFilePath, $imageData)) {
            // Insert into database
            $imgSql = "INSERT INTO jewellery_product_image (product_id, image_url, is_primary) 
                      VALUES (?, ?, 0)";
            $imgStmt = $conn->prepare($imgSql);
            $imgStmt->bind_param("is", $jewelryItemId, $targetFilePath);
            $imgStmt->execute();
        }
    }
}
```

## Usage Instructions

### For Users
1. **Open Camera**: Click the "Camera" button in the product form
2. **Capture Photo**: Click the camera button to take a photo
3. **Crop Image**: Drag the crop box to select desired area
4. **Adjust Size**: Drag corners to resize crop area
5. **Rotate**: Use rotate button if needed
6. **Accept**: Click accept to save the cropped image
7. **Add to Product**: Image will be added to product automatically

### For Developers
1. **Camera Button**: Add `id="cameraCaptureBtn"` to trigger camera
2. **Hidden Field**: Include `<input type="hidden" id="capturedImage" name="capturedImage" />`
3. **Image Preview**: Use `id="imagePreview"` container for previews
4. **Error Handling**: Check for camera permissions and device support

## Browser Compatibility
- **Chrome**: Full support
- **Firefox**: Full support
- **Safari**: Full support (iOS 11+)
- **Edge**: Full support
- **Mobile Browsers**: Full support with touch gestures

## Security Considerations
- **HTTPS Required**: Camera access requires secure connection
- **Permission Handling**: Graceful fallback for denied permissions
- **File Validation**: Server-side validation of uploaded images
- **Size Limits**: Configurable file size limits

## Error Handling
- **Camera Not Available**: Shows error message with instructions
- **Permission Denied**: Displays permission request guidance
- **Network Issues**: Graceful degradation for upload failures
- **File System Errors**: Proper error messages for storage issues

## Future Enhancements
- **Multiple Images**: Support for capturing multiple images
- **Advanced Cropping**: Aspect ratio locks and presets
- **Filters**: Basic image filters and adjustments
- **Quality Settings**: Configurable image quality options
- **Batch Processing**: Process multiple captured images

## Troubleshooting

### Common Issues
1. **Camera Not Working**: Check browser permissions and HTTPS
2. **Crop Not Working**: Ensure touch/mouse events are enabled
3. **Upload Fails**: Check server storage permissions and limits
4. **Image Quality**: Adjust camera resolution settings

### Debug Steps
1. Check browser console for JavaScript errors
2. Verify camera permissions in browser settings
3. Test with different browsers/devices
4. Check server logs for PHP errors
5. Verify file upload directory permissions 