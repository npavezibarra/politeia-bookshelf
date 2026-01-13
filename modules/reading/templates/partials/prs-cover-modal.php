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
                <div class="prs-cover-modal__title"><?php esc_html_e( 'Upload Book Cover', 'politeia-reading' ); ?></div>

                <div class="prs-cover-modal__grid">
                        <div class="prs-crop-wrap" id="drag-drop-area">
                                <div id="cropStage" class="prs-crop-stage" title="<?php echo esc_attr__( 'Drop JPEG or PNG file here', 'politeia-reading' ); ?>">
                                        <div id="cropPlaceholder" class="prs-crop-placeholder">
                                                <svg class="prs-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M4 14.9V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3.1"></path>
                                                        <path d="M16 16l-4-4-4 4"></path>
                                                        <path d="M12 12v9"></path>
                                                </svg>
                                                <p><?php esc_html_e( 'Drag JPEG or PNG here (220x350 Preview)', 'politeia-reading' ); ?></p>
                                                <span><?php esc_html_e( 'or click upload', 'politeia-reading' ); ?></span>
                                        </div>
                                        <img id="previewImage" src="" alt="<?php echo esc_attr__( 'Book Cover Preview', 'politeia-reading' ); ?>" style="display:none;">
                                        <div id="cropArea" class="prs-crop-area" style="display:none;">
                                                <div class="resize-handle corner nw"></div>
                                                <div class="resize-handle corner ne"></div>
                                                <div class="resize-handle corner sw"></div>
                                                <div class="resize-handle corner se"></div>
                                                <div class="resize-handle side n"></div>
                                                <div class="resize-handle side s"></div>
                                                <div class="resize-handle side e"></div>
                                                <div class="resize-handle side w"></div>
                                        </div>
                                </div>
                        </div>

                       <div class="prs-cover-controls" id="upload-settings-setting">

                               <div class="prs-file-input">
                                       <input type="file" id="fileInput" accept="image/jpeg, image/png" class="prs-hidden-input">
                                       <label for="fileInput" class="prs-btn prs-btn--ghost"><?php esc_html_e( 'Choose File', 'politeia-reading' ); ?></label>
                               </div>

                               <div class="prs-crop-controls">
                                       <p class="prs-crop-instructions"><?php esc_html_e( 'Drag or resize the selection on the image to crop.', 'politeia-reading' ); ?></p>
                               </div>

                               <span id="statusMessage" class="prs-cover-status"><?php esc_html_e( 'Awaiting file upload.', 'politeia-reading' ); ?></span>

                               <div class="prs-btn-group">
                                       <button class="prs-btn prs-btn--ghost" type="button" id="prs-cover-cancel"><?php esc_html_e( 'Cancel', 'politeia-reading' ); ?></button>
                                       <button class="prs-btn" type="button" id="prs-cover-save"><?php esc_html_e( 'Save', 'politeia-reading' ); ?></button>
                               </div>
                       </div>
                </div>
        </div>
</template>
