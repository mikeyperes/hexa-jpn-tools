<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\WpAdminComponents;

/**
 * Host-neutral gallery inspector for WordPress image attachments.
 */
final class MediaGalleryDetailsRenderer {
    /**
     * @param array<int,mixed> $attachments Attachment IDs, ACF image arrays, or attachment objects.
     * @param array<string,mixed> $args
     */
    public static function render( array $attachments, array $args = [] ): string {
        $args           = self::args( $args );
        $attachment_ids = self::attachment_ids( $attachments );

        ob_start();
        DynamicButton::render_assets();
        self::render_assets();
        $assets = (string) ob_get_clean();

        $attributes = self::root_attributes( $args );
        $body = '<div class="hpc-media-gallery-details" data-hpc-media-gallery-details' . $attributes . '>'
            . self::render_content( $attachment_ids, $args )
            . '</div>';

        $count = count( $attachment_ids );
        $meta  = '<span class="hpc-pill" data-hpc-gallery-count>'
            . esc_html( self::count_label( $count ) )
            . '</span>';
        $card  = CoreUi::detail_card(
            [
                'title'       => (string) $args['title'],
                'body_html'   => $body,
                'meta_html'   => $meta,
                'open'        => (bool) $args['open'],
                'persist_key' => (string) $args['persist_key'],
                'class'       => 'hpc-media-gallery-details-card',
            ]
        );

        return '<div class="hpc-ui hpc-media-gallery-details-shell" style="--hpc-media-gallery-preview-size:'
            . esc_attr( (string) $args['preview_pixels'] ) . 'px">'
            . $card
            . '</div>'
            . $assets;
    }

    /**
     * Render only the replaceable contents of the details panel.
     *
     * @param array<int,mixed> $attachments
     * @param array<string,mixed> $args
     */
    public static function render_content( array $attachments, array $args = [] ): string {
        $args           = self::args( $args );
        $attachment_ids = self::attachment_ids( $attachments );

        if ( [] === $attachment_ids ) {
            return '<p class="hpc-media-gallery-empty">No gallery images are currently selected.</p>';
        }

        $html = '<div class="hpc-media-gallery-toolbar">'
            . '<label><input type="checkbox" data-hpc-gallery-select-all> <span>Select all images</span></label>'
            . '<span role="status" aria-live="polite" data-hpc-gallery-selection-status>0 selected</span>'
            . '</div>'
            . '<div class="hpc-media-gallery-items">';

        foreach ( $attachment_ids as $attachment_id ) {
            $item = self::render_attachment( $attachment_id, $args );
            if ( '' !== $item ) {
                $html .= $item;
            }
        }

        return $html . '</div>';
    }

    /** @param array<int,mixed> $attachments @return array<int,int> */
    public static function attachment_ids( array $attachments ): array {
        $ids = [];
        foreach ( $attachments as $attachment ) {
            $id = 0;
            if ( is_numeric( $attachment ) ) {
                $id = (int) $attachment;
            } elseif ( is_object( $attachment ) && isset( $attachment->ID ) ) {
                $id = (int) $attachment->ID;
            } elseif ( is_array( $attachment ) ) {
                $id = (int) ( $attachment['ID'] ?? $attachment['id'] ?? 0 );
            }
            if ( $id > 0 && ! in_array( $id, $ids, true ) && wp_attachment_is_image( $id ) ) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @param array<string,mixed> $args */
    private static function render_attachment( int $attachment_id, array $args ): string {
        $sizes = self::image_sizes( $attachment_id );
        if ( [] === $sizes ) {
            return '';
        }

        $title       = trim( (string) get_the_title( $attachment_id ) );
        $full_url    = (string) ( $sizes['full']['url'] ?? '' );
        $preview     = wp_get_attachment_image_src( $attachment_id, (string) $args['preview_image_size'] );
        $preview_url = is_array( $preview ) && ! empty( $preview[0] ) ? (string) $preview[0] : $full_url;
        $filename    = '' !== $full_url ? wp_basename( (string) wp_parse_url( $full_url, PHP_URL_PATH ) ) : '';
        if ( '' === $title ) {
            $title = '' !== $filename ? $filename : 'Image ' . $attachment_id;
        }

        $html  = '<article class="hpc-media-gallery-item" data-hpc-gallery-item data-attachment-id="' . esc_attr( (string) $attachment_id ) . '">';
        $html .= '<div class="hpc-media-gallery-item-head"><label class="hpc-media-gallery-item-select">'
            . '<input type="checkbox" value="' . esc_attr( (string) $attachment_id ) . '" data-hpc-gallery-select>'
            . '<span class="hpc-media-gallery-thumb"><img src="' . esc_url( $preview_url ) . '" alt=""></span>'
            . '<span class="hpc-media-gallery-item-title"><strong>' . esc_html( $title ) . '</strong>'
            . '<small>Attachment #' . esc_html( (string) $attachment_id ) . ( '' !== $filename ? ' &middot; ' . esc_html( $filename ) : '' ) . '</small></span>'
            . '</label>';

        if ( ! empty( $args['allow_remove'] ) ) {
            $html .= '<div class="hpc-media-gallery-item-actions">'
                . DynamicButton::render(
                    [
                        'label'         => 'Delete',
                        'working_label' => 'Removing...',
                        'success_label' => 'Removed',
                        'error_label'   => 'Remove failed',
                        'class'         => 'hpc-button danger hpc-media-gallery-remove',
                        'render_assets' => false,
                        'attrs'         => [
                            'data-hpc-gallery-remove' => (string) $attachment_id,
                            'data-hpc-gallery-title'  => $title,
                            'aria-label'              => 'Remove ' . $title . ' from this gallery',
                            'title'                   => 'Remove from this gallery. The Media Library attachment will not be deleted.',
                        ],
                    ]
                )
                . '</div>';
        }
        $html .= '</div><div class="hpc-media-gallery-size-list">';

        foreach ( $sizes as $size_name => $size ) {
            $dimensions = '';
            if ( $size['width'] > 0 && $size['height'] > 0 ) {
                $dimensions = $size['width'] . ' x ' . $size['height'] . ' px';
            }
            $size_label = 'full' === $size_name ? 'Full' : ucwords( str_replace( [ '-', '_' ], ' ', $size_name ) );
            $html      .= '<div class="hpc-media-gallery-size">'
                . '<div class="hpc-media-gallery-size-label"><strong>' . esc_html( $size_label ) . '</strong>'
                . ( '' !== $dimensions ? '<span>' . esc_html( $dimensions ) . '</span>' : '' ) . '</div>'
                . '<a class="hpc-media-gallery-url hpc-external" href="' . esc_url( $size['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $size['url'] ) . '</a>'
                . '<div class="hpc-media-gallery-size-actions">'
                . DynamicButton::render(
                    [
                        'label'         => 'Copy image',
                        'working_label' => 'Copying image...',
                        'success_label' => 'Image copied',
                        'error_label'   => 'Copy failed',
                        'class'         => 'hpc-button secondary hpc-media-gallery-copy-image',
                        'render_assets' => false,
                        'attrs'         => [
                            'data-hpc-gallery-copy-image' => $size['url'],
                            'aria-label'                  => 'Copy ' . $size_label . ' image to clipboard',
                        ],
                    ]
                )
                . DynamicButton::render(
                    [
                        'label'         => 'Copy URL',
                        'working_label' => 'Copying URL...',
                        'success_label' => 'URL copied',
                        'error_label'   => 'Copy failed',
                        'class'         => 'hpc-button secondary hpc-media-gallery-copy-url',
                        'render_assets' => false,
                        'attrs'         => [
                            'data-hpc-gallery-copy'     => $size['url'],
                            'data-hpc-gallery-copy-url' => $size['url'],
                            'aria-label'                => 'Copy ' . $size_label . ' image URL to clipboard',
                        ],
                    ]
                )
                . '</div></div>';
        }

        return $html . '</div></article>';
    }

    /** @return array<string,array{url:string,width:int,height:int}> */
    private static function image_sizes( int $attachment_id ): array {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        $full_url = wp_get_attachment_url( $attachment_id );
        if ( ! is_string( $full_url ) || '' === $full_url ) {
            return [];
        }

        $sizes = [
            'full' => [
                'url'    => $full_url,
                'width'  => is_array( $metadata ) ? (int) ( $metadata['width'] ?? 0 ) : 0,
                'height' => is_array( $metadata ) ? (int) ( $metadata['height'] ?? 0 ) : 0,
            ],
        ];
        $generated = is_array( $metadata ) && isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] )
            ? $metadata['sizes']
            : [];
        $names = array_keys( $generated );
        usort( $names, [ self::class, 'compare_size_names' ] );

        foreach ( $names as $name ) {
            $source = wp_get_attachment_image_src( $attachment_id, (string) $name );
            if ( ! is_array( $source ) || empty( $source[0] ) ) {
                continue;
            }
            $data = is_array( $generated[ $name ] ?? null ) ? $generated[ $name ] : [];
            $sizes[ (string) $name ] = [
                'url'    => (string) $source[0],
                'width'  => (int) ( $data['width'] ?? $source[1] ?? 0 ),
                'height' => (int) ( $data['height'] ?? $source[2] ?? 0 ),
            ];
        }

        return $sizes;
    }

    private static function compare_size_names( string $left, string $right ): int {
        $order = [ 'thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048' ];
        $a     = array_search( $left, $order, true );
        $b     = array_search( $right, $order, true );
        $a     = false === $a ? PHP_INT_MAX : $a;
        $b     = false === $b ? PHP_INT_MAX : $b;

        return $a === $b ? strnatcasecmp( $left, $right ) : $a <=> $b;
    }

    /** @param array<string,mixed> $args @return array<string,mixed> */
    private static function args( array $args ): array {
        $preview_pixels = max( 72, min( 240, (int) ( $args['preview_pixels'] ?? 112 ) ) );
        $preview_size   = sanitize_key( (string) ( $args['preview_image_size'] ?? 'medium' ) );

        return array_merge(
            [
                'title'              => 'Details',
                'persist_key'        => 'media-gallery-details',
                'open'               => false,
                'preview_pixels'     => $preview_pixels,
                'preview_image_size' => '' !== $preview_size ? $preview_size : 'medium',
                'allow_remove'       => false,
                'ajax_url'           => '',
                'ajax_action'        => '',
                'nonce_field'        => 'nonce',
                'nonce'              => '',
                'context'            => '',
                'field_key'          => '',
                'live_refresh'       => false,
                'remove_confirm'     => 'Remove this image from the gallery? The Media Library attachment will remain available.',
            ],
            $args,
            [
                'preview_pixels'     => $preview_pixels,
                'preview_image_size' => '' !== $preview_size ? $preview_size : 'medium',
            ]
        );
    }

    /** @param array<string,mixed> $args */
    private static function root_attributes( array $args ): string {
        $map = [
            'data-hpc-gallery-ajax-url'       => $args['ajax_url'],
            'data-hpc-gallery-ajax-action'    => $args['ajax_action'],
            'data-hpc-gallery-nonce-field'    => $args['nonce_field'],
            'data-hpc-gallery-nonce'          => $args['nonce'],
            'data-hpc-gallery-context'        => $args['context'],
            'data-hpc-gallery-field-key'      => $args['field_key'],
            'data-hpc-gallery-remove-confirm' => $args['remove_confirm'],
        ];
        $attributes = ! empty( $args['live_refresh'] ) ? ' data-hpc-gallery-live-refresh="1"' : '';
        foreach ( $map as $name => $value ) {
            if ( '' !== (string) $value ) {
                $attributes .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
            }
        }

        return $attributes;
    }

    private static function count_label( int $count ): string {
        return $count . ' ' . ( 1 === $count ? 'image' : 'images' );
    }

    private static function render_assets(): void {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        ?>
        <style>
            .hpc-media-gallery-details-shell{margin-top:14px;max-width:100%}
            .hpc-media-gallery-details-card{margin-bottom:0}
            .hpc-media-gallery-details.is-refreshing{opacity:.68;pointer-events:none}
            .hpc-media-gallery-toolbar{align-items:center;border-bottom:1px solid var(--hpc-line);display:flex;gap:16px;justify-content:space-between;margin-bottom:12px;padding-bottom:12px}
            .hpc-media-gallery-toolbar label{align-items:center;display:inline-flex;font-weight:700;gap:7px}
            .hpc-media-gallery-toolbar [data-hpc-gallery-selection-status]{color:var(--hpc-muted);font-size:12px}
            .hpc-media-gallery-items{display:grid;gap:12px}
            .hpc-media-gallery-item{background:#fff;border:1px solid var(--hpc-line);border-radius:7px;min-width:0;overflow:hidden}
            .hpc-media-gallery-item.is-selected{border-color:var(--hpc-blue);box-shadow:0 0 0 1px var(--hpc-blue)}
            .hpc-media-gallery-item-head{align-items:center;background:#f8fafc;border-bottom:1px solid var(--hpc-line);display:grid;gap:12px;grid-template-columns:minmax(0,1fr) auto;padding:12px}
            .hpc-media-gallery-item-select{align-items:center;cursor:pointer;display:grid;gap:12px;grid-template-columns:auto var(--hpc-media-gallery-preview-size) minmax(0,1fr);min-width:0}
            .hpc-media-gallery-item-select input{height:16px;margin:0;width:16px}
            .hpc-media-gallery-thumb{background:#eef2f7;border:1px solid var(--hpc-line);border-radius:6px;display:block;height:var(--hpc-media-gallery-preview-size);overflow:hidden;width:var(--hpc-media-gallery-preview-size)}
            .hpc-media-gallery-thumb img{display:block;height:100%;object-fit:contain;width:100%}
            .hpc-media-gallery-item-title{min-width:0}
            .hpc-media-gallery-item-title strong,.hpc-media-gallery-item-title small{display:block;overflow-wrap:anywhere}
            .hpc-media-gallery-item-title strong{font-size:14px;margin-bottom:4px}
            .hpc-media-gallery-item-title small{color:var(--hpc-muted);font-size:11px}
            .hpc-media-gallery-item-actions{align-self:start;display:flex;justify-content:flex-end}
            .hpc-media-gallery-remove{font-size:11px;min-height:34px;padding:8px 11px;white-space:nowrap}
            .hpc-media-gallery-size-list{display:grid}
            .hpc-media-gallery-size{align-items:center;border-bottom:1px solid #edf1f6;display:grid;gap:10px;grid-template-columns:116px minmax(0,1fr) auto;padding:9px 12px}
            .hpc-media-gallery-size:last-child{border-bottom:0}
            .hpc-media-gallery-size-label strong,.hpc-media-gallery-size-label span{display:block}
            .hpc-media-gallery-size-label strong{font-size:12px}
            .hpc-media-gallery-size-label span{color:var(--hpc-muted);font-size:10px;margin-top:2px}
            .hpc-media-gallery-url{color:var(--hpc-blue);font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:11px;min-width:0;overflow-wrap:anywhere;user-select:text}
            .hpc-media-gallery-size-actions{align-items:center;display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}
            .hpc-media-gallery-copy-image,.hpc-media-gallery-copy-url{font-size:11px;min-height:32px;padding:7px 9px;white-space:nowrap}
            .hpc-media-gallery-copy-image .hpc-dynamic-button-spinner,.hpc-media-gallery-copy-url .hpc-dynamic-button-spinner,.hpc-media-gallery-remove .hpc-dynamic-button-spinner{height:16px;min-height:16px;min-width:16px;width:16px}
            .hpc-media-gallery-empty{color:var(--hpc-muted);margin:0}
            @media(max-width:782px){.hpc-media-gallery-item-head{align-items:stretch;grid-template-columns:minmax(0,1fr)}.hpc-media-gallery-item-select{grid-template-columns:auto 88px minmax(0,1fr)}.hpc-media-gallery-thumb{height:88px;width:88px}.hpc-media-gallery-item-actions{justify-content:flex-start;padding-left:28px}.hpc-media-gallery-size{align-items:start;grid-template-columns:minmax(0,1fr)}.hpc-media-gallery-size-actions{justify-content:flex-start}.hpc-media-gallery-toolbar{align-items:flex-start;flex-direction:column;gap:7px}}
        </style>
        <script>
        (function(){
            if(window.hexaCoreMediaGalleryDetailsReady)return;
            window.hexaCoreMediaGalleryDetailsReady=true;
            function legacyCopy(value){
                return new Promise(function(resolve,reject){
                    var input=document.createElement('textarea');
                    input.value=value;input.setAttribute('readonly','');input.style.position='fixed';input.style.opacity='0';
                    document.body.appendChild(input);input.select();
                    try{document.execCommand('copy')?resolve():reject(new Error('Copy command failed.'))}catch(error){reject(error)}
                    document.body.removeChild(input);
                });
            }
            function copyText(value){
                if(navigator.clipboard&&window.isSecureContext){
                    return navigator.clipboard.writeText(value).catch(function(){return legacyCopy(value)});
                }
                return legacyCopy(value);
            }
            function imageBlobAsPng(url){
                return fetch(url,{credentials:'same-origin'}).then(function(response){
                    if(!response.ok)throw new Error('Image request failed.');return response.blob();
                }).then(function(blob){
                    var load=window.createImageBitmap?window.createImageBitmap(blob):new Promise(function(resolve,reject){
                        var image=new Image(),objectUrl=URL.createObjectURL(blob);
                        image.onload=function(){URL.revokeObjectURL(objectUrl);resolve(image)};
                        image.onerror=function(){URL.revokeObjectURL(objectUrl);reject(new Error('Image could not be decoded.'))};
                        image.src=objectUrl;
                    });
                    return load.then(function(image){
                        var width=image.naturalWidth||image.width,height=image.naturalHeight||image.height;
                        var canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;
                        var context=canvas.getContext('2d');if(!context)throw new Error('Canvas is unavailable.');
                        context.drawImage(image,0,0,width,height);if(image.close)image.close();
                        return new Promise(function(resolve,reject){canvas.toBlob(function(png){png?resolve(png):reject(new Error('PNG conversion failed.'))},'image/png')});
                    });
                });
            }
            function copyImage(url){
                if(!navigator.clipboard||!navigator.clipboard.write||!window.ClipboardItem){return Promise.reject(new Error('Image clipboard is unavailable.'))}
                var pending=imageBlobAsPng(url);
                try{return navigator.clipboard.write([new ClipboardItem({'image/png':pending})]).catch(function(){return pending.then(function(blob){return navigator.clipboard.write([new ClipboardItem({'image/png':blob})])})})}
                catch(error){return pending.then(function(blob){return navigator.clipboard.write([new ClipboardItem({'image/png':blob})])})}
            }
            function updateCount(root){
                var count=root.querySelectorAll('[data-hpc-gallery-item]').length;
                var node=root.closest('.hpc-media-gallery-details-shell');node=node?node.querySelector('[data-hpc-gallery-count]'):null;
                if(node)node.textContent=count+' '+(count===1?'image':'images');
            }
            function updateSelection(root){
                if(!root)return;
                var boxes=Array.prototype.slice.call(root.querySelectorAll('[data-hpc-gallery-select]'));
                var selected=boxes.filter(function(box){return box.checked});
                boxes.forEach(function(box){var item=box.closest('[data-hpc-gallery-item]');if(item)item.classList.toggle('is-selected',box.checked)});
                var all=root.querySelector('[data-hpc-gallery-select-all]');
                if(all){all.checked=boxes.length>0&&selected.length===boxes.length;all.indeterminate=selected.length>0&&selected.length<boxes.length}
                var status=root.querySelector('[data-hpc-gallery-selection-status]');
                if(status)status.textContent=selected.length+' selected';
                updateCount(root);
            }
            function request(root,operation,values){
                var url=root.dataset.hpcGalleryAjaxUrl,action=root.dataset.hpcGalleryAjaxAction;
                if(!url||!action)return Promise.reject(new Error('Gallery action is not configured.'));
                var data=new URLSearchParams();data.set('action',action);data.set('operation',operation);
                data.set(root.dataset.hpcGalleryNonceField||'nonce',root.dataset.hpcGalleryNonce||'');
                data.set('context',root.dataset.hpcGalleryContext||'');data.set('field_key',root.dataset.hpcGalleryFieldKey||'');
                Object.keys(values||{}).forEach(function(key){var value=values[key];if(Array.isArray(value)){value.forEach(function(item){data.append(key+'[]',item)})}else{data.set(key,value)}});
                return fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:data.toString()})
                    .then(function(response){return response.json()})
                    .then(function(payload){if(!payload||!payload.success){var message=payload&&payload.data&&payload.data.message?payload.data.message:'Gallery action failed.';throw new Error(message)}return payload.data||{}});
            }
            function nativeGallery(root){return root.closest('.acf-field')}
            function nativeIds(root){
                var field=nativeGallery(root);if(!field)return [];
                return Array.prototype.slice.call(field.querySelectorAll('.acf-gallery-attachments .acf-gallery-attachment[data-id]'))
                    .map(function(item){return item.getAttribute('data-id')||''}).filter(Boolean);
            }
            function refresh(root){
                if(!root||root.dataset.hpcGalleryLiveRefresh!=='1')return Promise.resolve();
                var sequence=(parseInt(root.dataset.hpcGalleryRefreshSequence||'0',10)||0)+1;root.dataset.hpcGalleryRefreshSequence=String(sequence);root.classList.add('is-refreshing');
                return request(root,'refresh',{attachment_ids:nativeIds(root)}).then(function(data){
                    if(root.dataset.hpcGalleryRefreshSequence!==String(sequence))return;
                    root.innerHTML=data.content_html||'<p class="hpc-media-gallery-empty">No gallery images are currently selected.</p>';updateSelection(root);
                }).catch(function(error){root.dataset.hpcGalleryRefreshError=error.message}).finally(function(){if(root.dataset.hpcGalleryRefreshSequence===String(sequence))root.classList.remove('is-refreshing')});
            }
            function scheduleRefresh(root){clearTimeout(root._hpcGalleryRefreshTimer);root._hpcGalleryRefreshTimer=setTimeout(function(){refresh(root)},140)}
            function syncNativeRemoval(root,id){
                var field=nativeGallery(root);if(!field)return;
                var item=Array.prototype.find.call(field.querySelectorAll('.acf-gallery-attachment[data-id]'),function(candidate){return candidate.getAttribute('data-id')===String(id)});
                if(!item)return;
                var remove=item.querySelector('[data-name="remove"],.acf-gallery-remove,.acf-icon.-cancel');
                if(remove){remove.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true,view:window}))}else{item.remove()}
            }
            function bindNativeObserver(root){
                if(root.dataset.hpcGalleryObserverBound==='1')return;var field=nativeGallery(root);if(!field)return;
                root.dataset.hpcGalleryObserverBound='1';
                var observer=new MutationObserver(function(mutations){
                    var changed=mutations.some(function(mutation){
                        var target=mutation.target&&mutation.target.nodeType===1?mutation.target:null;
                        if(target&&target.closest('.acf-gallery-attachments'))return true;
                        return Array.prototype.some.call(mutation.addedNodes,function(node){return node.nodeType===1&&(node.matches('.acf-gallery-attachment')||node.querySelector('.acf-gallery-attachment'))})
                            ||Array.prototype.some.call(mutation.removedNodes,function(node){return node.nodeType===1&&(node.matches('.acf-gallery-attachment')||node.querySelector('.acf-gallery-attachment'))});
                    });
                    if(changed)scheduleRefresh(root);
                });
                observer.observe(field,{childList:true,subtree:true});root._hpcGalleryObserver=observer;
            }
            document.addEventListener('change',function(event){
                var select=event.target.closest('[data-hpc-gallery-select]');
                if(select){updateSelection(select.closest('[data-hpc-media-gallery-details]'));return}
                var all=event.target.closest('[data-hpc-gallery-select-all]');
                if(all){var root=all.closest('[data-hpc-media-gallery-details]');root.querySelectorAll('[data-hpc-gallery-select]').forEach(function(box){box.checked=all.checked});updateSelection(root)}
            });
            document.addEventListener('click',function(event){
                var api=window.HexaWpCoreDynamicButton;
                var imageButton=event.target.closest('[data-hpc-gallery-copy-image]');
                if(imageButton){event.preventDefault();if(api)api.start(imageButton,'Copying image...');copyImage(imageButton.dataset.hpcGalleryCopyImage||'').then(function(){if(api)api.success(imageButton,'Image copied')}).catch(function(){if(api)api.error(imageButton,'Copy failed')});return}
                var urlButton=event.target.closest('[data-hpc-gallery-copy-url],[data-hpc-gallery-copy]');
                if(urlButton){event.preventDefault();if(api)api.start(urlButton,'Copying URL...');copyText(urlButton.dataset.hpcGalleryCopyUrl||urlButton.dataset.hpcGalleryCopy||'').then(function(){if(api)api.success(urlButton,'URL copied')}).catch(function(){if(api)api.error(urlButton,'Copy failed')});return}
                var removeButton=event.target.closest('[data-hpc-gallery-remove]');
                if(!removeButton)return;event.preventDefault();
                var root=removeButton.closest('[data-hpc-media-gallery-details]'),id=removeButton.dataset.hpcGalleryRemove||'',message=root.dataset.hpcGalleryRemoveConfirm||'';
                if(message&&!window.confirm(message))return;if(api)api.start(removeButton,'Removing...');
                request(root,'remove',{attachment_id:id}).then(function(){
                    if(api)api.success(removeButton,'Removed',false);
                    window.setTimeout(function(){syncNativeRemoval(root,id);var item=removeButton.closest('[data-hpc-gallery-item]');if(item)item.remove();if(!root.querySelector('[data-hpc-gallery-item]'))root.innerHTML='<p class="hpc-media-gallery-empty">No gallery images are currently selected.</p>';updateSelection(root)},260);
                }).catch(function(){if(api)api.error(removeButton,'Remove failed')});
            });
            function bindAll(scope){
                var roots=[];
                if(scope&&scope.matches&&scope.matches('[data-hpc-media-gallery-details]'))roots.push(scope);
                if(scope&&scope.querySelectorAll)roots=roots.concat(Array.prototype.slice.call(scope.querySelectorAll('[data-hpc-media-gallery-details]')));
                roots.forEach(function(root){updateSelection(root);bindNativeObserver(root)});
            }
            bindAll(document);
            if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){bindAll(document)},{once:true});
            if(window.acf&&typeof window.acf.addAction==='function')window.acf.addAction('append',function(element){bindAll(element&&element[0]?element[0]:element)});
        })();
        </script>
        <?php
    }
}
