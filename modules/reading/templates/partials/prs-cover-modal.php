<?php
/**
 * Cover modal template markup.
 *
 * @package Politeia_Reading
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
?>
<template id="prs-cover-modal-template">
        <div class="prs-cover-modal__content">
                <div class="prs-cover-modal__title">Upload Book Cover</div>

                <div class="prs-cover-modal__grid">
                        <div class="prs-crop-wrap" id="drag-drop-area">
                                <div id="cropStage" class="prs-crop-stage" title="Drop JPEG or PNG file here">
                                        <div id="cropPlaceholder" class="prs-crop-placeholder">
                                                <svg class="prs-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M4 14.9V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3.1"></path>
                                                        <path d="M16 16l-4-4-4 4"></path>
                                                        <path d="M12 12v9"></path>
                                                </svg>
                                                <p>Drag JPEG or PNG here (220x350 Preview)</p>
                                                <span>or click upload</span>
                                        </div>
                                        <img id="previewImage" src="" alt="Book Cover Preview" style="display:none;">
                                </div>
                        </div>

                        <div class="prs-cover-controls" id="upload-settings-setting">
                                <div class="prs-file-input">
                                        <input type="file" id="fileInput" accept="image/jpeg, image/png" class="prs-hidden-input">
                                        <label for="fileInput" class="prs-btn prs-btn--ghost">Choose File</label>
                                </div>

                                <div class="prs-crop-controls">
                                        <label for="zoomSlider">Zoom (1x - 4x)</label>
                                        <input type="range" id="zoomSlider" min="1" max="4" step="0.01" value="1" disabled>
                                </div>

                                <span id="statusMessage" class="prs-cover-status">Awaiting file upload.</span>

                                <div class="prs-btn-group">
                                        <button class="prs-btn prs-btn--ghost" type="button" id="prs-cover-cancel">Cancel</button>
                                        <button class="prs-btn" type="button" id="prs-cover-save">Save</button>
                                </div>
                        </div>
                </div>
        </div>
</template>
