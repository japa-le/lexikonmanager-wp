<?php
/*
Plugin Name: Lexikon Manager
Description: Verwaltet Lexikon-Einträge und zentrale Texte via Shortcodes.
Version: 1.2
Author: Janos
*/

if ( ! defined('ABSPATH') ) exit;

/**
 * Admin: Quick Edit JS nur für CPT "lexikon"
 */
function lexikon_enqueue_quickedit_script($hook) {
    if ('edit.php' !== $hook) return;

    $screen = get_current_screen();
    if (isset($screen->post_type) && 'lexikon' === $screen->post_type) {
        $quickedit_path = plugin_dir_path(__FILE__) . 'lexikon_quick_edit.js';
        $quickedit_ver  = file_exists($quickedit_path) ? (string) filemtime($quickedit_path) : '1.0';

        wp_enqueue_script(
            'lexikon_quick_edit',
            plugin_dir_url(__FILE__) . 'lexikon_quick_edit.js',
            array('jquery', 'inline-edit-post'),
            $quickedit_ver,
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'lexikon_enqueue_quickedit_script');

/**
 * Frontend: Lexikon JS auf der Lexikon-Seite laden
 */
function lexikon_enqueue_frontend_script() {
    // Robust laden: wenn Seite/Slug nicht exakt passt, Script trotzdem im Frontend verfügbar halten.
    if (is_admin()) return;

    $frontend_js_path = plugin_dir_path(__FILE__) . 'lexikon.js';
    $frontend_js_ver  = file_exists($frontend_js_path) ? (string) filemtime($frontend_js_path) : '1.0';

    wp_enqueue_script(
        'lexikon-script',
        plugin_dir_url(__FILE__) . 'lexikon.js',
        array('jquery'),
        $frontend_js_ver,
        true
    );
}
add_action('wp_enqueue_scripts', 'lexikon_enqueue_frontend_script');


/**
 * Admin: CPT "lexikon" Liste immer alphabetisch
 */
function lexikon_orderby_title( $query ) {
    if ( is_admin() && $query->is_main_query() && 'lexikon' === $query->get('post_type') ) {
        $query->set( 'orderby', 'title' );
        $query->set( 'order', 'ASC' );
    }
}
add_action( 'pre_get_posts', 'lexikon_orderby_title' );


/**
 * 1) Custom Post Type registrieren
 */
function lexikon_register_post_type() {
    $labels = array(
        'name'               => 'Lexikon',
        'singular_name'      => 'Lexikon-Eintrag',
        'add_new'            => 'Neuen Eintrag hinzufügen',
        'add_new_item'       => 'Neuen Lexikon-Eintrag hinzufügen',
        'edit_item'          => 'Eintrag bearbeiten',
        'new_item'           => 'Neuer Eintrag',
        'view_item'          => 'Eintrag ansehen',
        'search_items'       => 'Einträge durchsuchen',
        'not_found'          => 'Keine Einträge gefunden',
        'not_found_in_trash' => 'Keine Einträge im Papierkorb gefunden'
    );

    $args = array(
        'labels'       => $labels,
        'public'       => false,
        'show_ui'      => true,
        'supports'     => array('title', 'editor'),
        'menu_icon'    => 'dashicons-book',
    );

    register_post_type('lexikon', $args);
}
add_action('init', 'lexikon_register_post_type');


/**
 * 2) Meta-Boxen hinzufügen
 */
function lexikon_add_meta_boxes() {
    add_meta_box('lexikon_meta', 'Lexikon Einstellungen', 'lexikon_meta_box_callback', 'lexikon', 'normal', 'high');
}
add_action('add_meta_boxes', 'lexikon_add_meta_boxes');

/**
 * Admin: Mediathek für Bild-Upload im Lexikon-Editor
 */
function lexikon_enqueue_media_uploader($hook) {
    if ( ! in_array($hook, array('post.php', 'post-new.php'), true) ) return;

    $screen = get_current_screen();
    if ( ! isset($screen->post_type) || 'lexikon' !== $screen->post_type ) return;

    wp_enqueue_media();
    wp_add_inline_script('jquery', '
        jQuery(function($){
            var frame;

            $(document).on("click", "#lexikon_image_upload_btn", function(e){
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: "Bild auswählen",
                    button: { text: "Bild verwenden" },
                    multiple: false,
                    library: { type: "image" }
                });

                frame.on("select", function(){
                    var attachment = frame.state().get("selection").first().toJSON();
                    $("#lexikon_image_url").val(attachment.url);
                    $("#lexikon_image_preview").attr("src", attachment.url).show();
                });

                frame.open();
            });

            $(document).on("click", "#lexikon_image_remove_btn", function(e){
                e.preventDefault();
                $("#lexikon_image_url").val("");
                $("#lexikon_image_preview").attr("src", "").hide();
            });
        });
    ');
}
add_action('admin_enqueue_scripts', 'lexikon_enqueue_media_uploader');

function lexikon_meta_box_callback($post) {
    $buchstabe = get_post_meta($post->ID, '_lexikon_buchstabe', true);

    $tabs = get_post_meta($post->ID, '_lexikon_tabs', true);
    if (!is_array($tabs)) $tabs = array();

    $video_url     = get_post_meta($post->ID, '_lexikon_video_url', true);
    $blog_post_id  = (int) get_post_meta($post->ID, '_lexikon_blog_post_id', true);
    $file_url      = get_post_meta($post->ID, '_lexikon_file_url', true);
    $image_url     = get_post_meta($post->ID, '_lexikon_image_url', true);

    wp_nonce_field('lexikon_save_meta', 'lexikon_meta_nonce');
    ?>
    <p>
      <label for="lexikon_buchstabe"><strong>Buchstabe:</strong></label>
      <input type="text" id="lexikon_buchstabe" name="lexikon_buchstabe" value="<?php echo esc_attr($buchstabe); ?>" placeholder="z.B. A">
    </p>

    <p>
      <strong>Tabs auswählen:</strong><br>
      <label>
        <input type="checkbox" name="lexikon_tabs[]" value="verbraucher" <?php checked(in_array('verbraucher', $tabs, true)); ?>> Verbraucherinsolvenz
      </label><br>
      <label>
        <input type="checkbox" name="lexikon_tabs[]" value="regel" <?php checked(in_array('regel', $tabs, true)); ?>> Regelinsolvenz
      </label><br>
      <label>
        <input type="checkbox" name="lexikon_tabs[]" value="firmen" <?php checked(in_array('firmen', $tabs, true)); ?>> Firmeninsolvenz
      </label>
    </p>

    <hr>

    <p>
      <label for="lexikon_video_url"><strong>Erklärvideo (URL, optional):</strong></label><br>
      <input type="text"
             id="lexikon_video_url"
             name="lexikon_video_url"
             class="large-text"
             placeholder="https://deine-seite.de/wp-content/uploads/video.mp4"
             value="<?php echo esc_attr($video_url); ?>">
      <small>Video in die Mediathek hochladen → URL kopieren → hier einfügen.</small>
    </p>

    <p>
      <label for="lexikon_blog_post_id"><strong>Blog Beitrag ID (optional):</strong></label><br>
      <input type="number"
             id="lexikon_blog_post_id"
             name="lexikon_blog_post_id"
             class="small-text"
             placeholder="123"
             value="<?php echo esc_attr($blog_post_id); ?>">
      <small>WP-Post-ID vom Blogbeitrag (Featured Image + Titel + Datum werden automatisch gezogen).</small>
    </p>

    <p>
      <label for="lexikon_file_url"><strong>Dokumentenvorlage (Download-URL, optional):</strong></label><br>
      <input type="text"
             id="lexikon_file_url"
             name="lexikon_file_url"
             class="large-text"
             placeholder="https://insolvenzo.eu/wp-content/uploads/template.pdf"
             value="<?php echo esc_attr($file_url); ?>">
      <small>PDF/DOCX in die Mediathek hochladen → URL kopieren → hier einfügen.</small>
    </p>

    <p>
      <label for="lexikon_image_url"><strong>Infografik/Bild (optional):</strong></label><br>
      <input type="hidden" id="lexikon_image_url" name="lexikon_image_url" value="<?php echo esc_attr($image_url); ?>">

      <img
        id="lexikon_image_preview"
        src="<?php echo esc_url($image_url); ?>"
        style="max-width:220px; max-height:160px; margin:8px 0; display:<?php echo empty($image_url) ? 'none' : 'block'; ?>; border:1px solid #ddd;"
        alt="Vorschau"
      >

      <button type="button" class="button" id="lexikon_image_upload_btn">Bild auswählen/hochladen</button>
      <button type="button" class="button" id="lexikon_image_remove_btn">Bild entfernen</button>
      <br><small>Wird im Frontend als Button angezeigt und öffnet in einer Lightbox.</small>
    </p>
    <?php
}

function lexikon_save_meta_box_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (get_post_type($post_id) !== 'lexikon') return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    if ( ! isset($_POST['lexikon_meta_nonce']) || ! wp_verify_nonce($_POST['lexikon_meta_nonce'], 'lexikon_save_meta') ) {
        return;
    }

    if (isset($_POST['lexikon_buchstabe'])) {
        update_post_meta($post_id, '_lexikon_buchstabe', sanitize_text_field($_POST['lexikon_buchstabe']));
    }

    if (isset($_POST['lexikon_tabs'])) {
        $tabs = array_filter(array_map('sanitize_text_field', (array) $_POST['lexikon_tabs']));
        update_post_meta($post_id, '_lexikon_tabs', $tabs);
    } else {
        delete_post_meta($post_id, '_lexikon_tabs');
    }

    if (isset($_POST['lexikon_video_url'])) {
        $video_url = esc_url_raw($_POST['lexikon_video_url']);
        if ($video_url) update_post_meta($post_id, '_lexikon_video_url', $video_url);
        else delete_post_meta($post_id, '_lexikon_video_url');
    }

    if (isset($_POST['lexikon_blog_post_id'])) {
        $blog_post_id = (int) $_POST['lexikon_blog_post_id'];
        if ($blog_post_id > 0) update_post_meta($post_id, '_lexikon_blog_post_id', $blog_post_id);
        else delete_post_meta($post_id, '_lexikon_blog_post_id');
    }

    if (isset($_POST['lexikon_file_url'])) {
        $file_url = esc_url_raw($_POST['lexikon_file_url']);
        if ($file_url) update_post_meta($post_id, '_lexikon_file_url', $file_url);
        else delete_post_meta($post_id, '_lexikon_file_url');
    }

    if (isset($_POST['lexikon_image_url'])) {
        $image_url = esc_url_raw($_POST['lexikon_image_url']);
        if ($image_url) update_post_meta($post_id, '_lexikon_image_url', $image_url);
        else delete_post_meta($post_id, '_lexikon_image_url');
    }
}
add_action('save_post', 'lexikon_save_meta_box_data');


/**
 * 3) Custom Columns in Admin (extended for Quick Edit)
 */
function lexikon_custom_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['buchstabe']  = 'Buchstabe';
            $new_columns['insolvency'] = 'Insolvenz Typ';
        }
    }
    return $new_columns;
}
add_filter('manage_lexikon_posts_columns', 'lexikon_custom_columns');

function lexikon_custom_columns_content($column, $post_id) {
    if ($column === 'buchstabe') {
        $letter = get_post_meta($post_id, '_lexikon_buchstabe', true);
        echo !empty($letter) ? esc_html($letter) : '—';
    }

    if ($column === 'insolvency') {
        $tabs = get_post_meta($post_id, '_lexikon_tabs', true);
        if (!empty($tabs) && is_array($tabs)) {
            echo esc_html(implode(', ', $tabs));
            echo '<div class="lexikon_tabs_data" style="display:none;">' . esc_attr(implode(',', $tabs)) . '</div>';
        } else {
            echo '—';
        }
    }
}
add_action('manage_lexikon_posts_custom_column', 'lexikon_custom_columns_content', 10, 2);

function lexikon_quick_edit_custom_box($column_name, $post_type) {
    if ($post_type !== 'lexikon') return;

    if ($column_name === 'buchstabe') {
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-group">
                <label>
                    <span class="title">Buchstabe</span>
                    <input type="text" name="lexikon_buchstabe" value="">
                </label>
            </div>
        </fieldset>
        <?php
    }

    if ($column_name === 'insolvency') {
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-group">
                <label class="alignleft">
                    <span class="title">Insolvenz Typ</span>
                    <span class="input-text-wrap">
                        <input type="hidden" name="lexikon_tabs[]" value="">
                        <label><input type="checkbox" name="lexikon_tabs[]" value="verbraucher"> Verbraucherinsolvenz</label><br>
                        <label><input type="checkbox" name="lexikon_tabs[]" value="regel"> Regelinsolvenz</label><br>
                        <label><input type="checkbox" name="lexikon_tabs[]" value="firmen"> Firmeninsolvenz</label>
                    </span>
                </label>
            </div>
        </fieldset>
        <?php
    }
}
add_action('quick_edit_custom_box', 'lexikon_quick_edit_custom_box', 10, 2);


/**
 * Frontend: Video Placeholder
 */
function lexikon_get_video_placeholder_html( $video_url ) {
    ob_start();
    ?>
    <div class="lexikon-video" data-video-url="<?php echo esc_url($video_url); ?>">
        <button type="button" class="lexikon-video-btn">
            <i class="fa-solid fa-play" aria-hidden="true"></i>
            <span>Erklärvideo</span>
        </button>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Frontend: Lightbox Assets einmalig ausgeben
 */
function lexikon_get_lightbox_assets_html() {
    static $printed = false;

    if ($printed) {
        return '';
    }
    $printed = true;

    return <<<HTML
<div id="lexikon-lightbox" style="display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;">
  <button type="button" id="lexikon-lightbox-close" aria-label="Schließen" style="position:absolute;top:18px;right:24px;background:none;border:none;color:#fff;font-size:2.2rem;cursor:pointer;line-height:1;">&times;</button>
  <img id="lexikon-lightbox-img" src="" alt="Infografik" style="background:white;max-width:90vw;max-height:88vh;display:block;border-radius:6px;box-shadow:0 4px 40px rgba(0,0,0,.6);padding: 15px;">
</div>
<style>
  #lexikon-lightbox.is-open{display:flex!important;}
</style>
<script>
  (function(){
    var lb = document.getElementById('lexikon-lightbox');
    var img = document.getElementById('lexikon-lightbox-img');
    var closeBtn = document.getElementById('lexikon-lightbox-close');
    if(!lb || !img || !closeBtn) return;

    // Falls Lightbox in einem versteckten Elementor-Tab liegt, an <body> hängen
    if (lb.parentNode !== document.body) {
      document.body.appendChild(lb);
    }

    function openLightbox(src){
      if(!src) return;
      img.src = src;
      lb.style.display = 'flex';
      lb.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    }

    function closeLightbox(){
      lb.style.display = 'none';
      lb.classList.remove('is-open');
      img.src = '';
      document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e){
      var btn = e.target.closest('.lexikon-image-btn');
      if (btn) {
        openLightbox(btn.getAttribute('data-image'));
        return;
      }
      if (e.target === lb || e.target === closeBtn) {
        closeLightbox();
      }
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') {
        closeLightbox();
      }
    });
  })();
</script>
HTML;
}

/**
 * Frontend: Ressourcen (Video + Blog Preview + Download)
 */
function lexikon_get_resources_html( $video_url = '', $blog_post_id = 0, $file_url = '', $image_url = '' ) {
    $blog_post_id = (int) $blog_post_id;

    if ( empty($video_url) && empty($blog_post_id) && empty($file_url) && empty($image_url) ) {
        return '';
    }

    $out = lexikon_get_lightbox_assets_html();
    $out .= '<div class="lexikon-resources-or">';
    $out .= '<div class="lexikon-resources">';

    // Video Button (wie gehabt)
    if ( ! empty($video_url) ) {
        $out .= lexikon_get_video_placeholder_html( $video_url );
    }

     /**
     * ------------------------------------------------------------------
     * NEU: Blog + Download als Buttons (optisch wie Video-Button)
     * ------------------------------------------------------------------
     */

    if ( $blog_post_id > 0 ) {
        $p = get_post($blog_post_id);
        if ( $p && $p->post_status === 'publish' ) {
            $out .= '<div class="lexikon-resource">';
            $out .= '<a class="lexikon-blog-btn" href="' . esc_url(get_permalink($p)) . '" target="_blank" rel="noopener">';
            $out .= '<i class="fa-solid fa-newspaper" aria-hidden="true"></i>';
            $out .= '<span>Blog Artikel</span>';
            $out .= '</a>';
            $out .= '</div>';
        }
    }

    $out .= '</div>';

    if ( ! empty($file_url) || ! empty($image_url) ) {
        $out .= '<div class="lexikon-resource-file">';
        if ( ! empty($file_url) ) {
            $out .= '<a class="lexikon-download-btn" href="' . esc_url($file_url) . '" download>';
            $out .= '<i class="fa-solid fa-download" aria-hidden="true"></i>';
            $out .= '<span>Download Dokument</span>';
            $out .= '</a>';
        }
        if ( ! empty($image_url) ) {
            $out .= '<button type="button" class="lexikon-image-btn" data-image="' . esc_url($image_url) . '">';
            $out .= '<i class="fa-solid fa-image" aria-hidden="true"></i>';
            $out .= '<span>Infografik</span>';
            $out .= '</button>';
        }
        $out .= '</div>';
    }

    $out .= '</div>';

    return $out;

    /**
     * ------------------------------------------------------------------
     * ALT: Blog Preview (auskommentiert, NICHT gelöscht)
     * ------------------------------------------------------------------
     */
    /*
    if ( $blog_post_id > 0 ) {
        $p = get_post($blog_post_id);

        if ( $p && $p->post_status === 'publish' ) {
            $permalink = get_permalink($p);
            $title     = get_the_title($p);
            $date      = get_the_date(get_option('date_format'), $p);

            $thumb = '';
            if ( has_post_thumbnail($p) ) {
                $thumb = get_the_post_thumbnail($p, 'thumbnail', array(
                    'class'    => 'lexikon-blog-thumb',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                ));
            }

            $out .= '<a class="lexikon-blog-preview" href="' . esc_url($permalink) . '" target="_blank" rel="noopener">';
            if ( $thumb ) {
                $out .= '<div class="lexikon-blog-media">' . $thumb . '</div>';
            }
            $out .= '<div class="lexikon-blog-meta">';
            $out .= '<div class="lexikon-blog-label">Blog Artikel:</div>';
            $out .= '<div class="lexikon-blog-title">' . esc_html($title) . '</div>';
            $out .= '<div class="lexikon-blog-date">' . esc_html($date) . '</div>';
            $out .= '</div>';
            $out .= '</a>';
        }
    }

    if ( ! empty($file_url) ) {
        $filename = basename(parse_url($file_url, PHP_URL_PATH));
        $out .= '<a class="lexikon-download-btn" href="' . esc_url($file_url) . '" download>';
        $out .= '<i class="fa-solid fa-download" aria-hidden="true"></i> ';
        $out .= esc_html($filename);
        $out .= '</a>';
    }
    */

}

/**
 * 5) Shortcodes: [lexikon_display type="verbraucher|regel|firmen"]
 */
function lexikon_display_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => 'verbraucher'
    ), $atts, 'lexikon_display');

    $args = array(
        'post_type'      => 'lexikon',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_lexikon_tabs',
                'value'   => $atts['type'],
                'compare' => 'LIKE'
            )
        ),
        'orderby'        => 'title',
        'order'          => 'ASC'
    );

    $query  = new WP_Query($args);
    $groups = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $post_id = get_the_ID();

            $letter = get_post_meta($post_id, '_lexikon_buchstabe', true);
            $letter = strtoupper($letter);
            if (empty($letter)) {
                $letter = strtoupper(substr(get_the_title(), 0, 1));
            }

            if (!isset($groups[$letter])) $groups[$letter] = array();

            $groups[$letter][] = array(
                'title'        => get_the_title(),
                'content'      => apply_filters('the_content', get_the_content()),
                'video_url'    => get_post_meta($post_id, '_lexikon_video_url', true),
                'blog_post_id' => (int) get_post_meta($post_id, '_lexikon_blog_post_id', true),
                'file_url'     => get_post_meta($post_id, '_lexikon_file_url', true),
                'image_url'    => get_post_meta($post_id, '_lexikon_image_url', true),
            );
        }
        wp_reset_postdata();
    }

    ksort($groups);

    $output = '';
    foreach ($groups as $letter => $posts) {
        $class = strtolower($letter) . '-text';

        $output .= '<div class="alphabets">' . "\n";
        $output .= '<span class="' . esc_attr($class) . '"><strong>' . esc_html($letter) . '</strong></span>' . "\n";
        $output .= '<ul>' . "\n";

        foreach ($posts as $post) {
            $output .= '<li class="insvi"><div>';
            $output .= '<strong>' . esc_html($post['title']) . ':</strong> ' . $post['content'] . '</div>';

            $output .= lexikon_get_resources_html(
                $post['video_url'],
                $post['blog_post_id'],
                $post['file_url'],
                $post['image_url']
            );

            $output .= '</li>' . "\n";
        }

        $output .= '</ul>' . "\n";
        $output .= '</div>' . "\n";
    }

    return $output;
}
add_shortcode('lexikon_display', 'lexikon_display_shortcode');


/**
 * Shortcode: [lexikon_search type="verbraucher|regel|firmen"]
 */
function lexikon_search_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => 'verbraucher'
    ), $atts, 'lexikon_search');

    $args = array(
        'post_type'      => 'lexikon',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_lexikon_tabs',
                'value'   => $atts['type'],
                'compare' => 'LIKE'
            )
        ),
        'orderby'        => 'title',
        'order'          => 'ASC'
    );

    $query  = new WP_Query($args);
    $groups = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $post_id = get_the_ID();

            $letter = get_post_meta($post_id, '_lexikon_buchstabe', true);
            $letter = strtoupper($letter);
            if (empty($letter)) {
                $letter = strtoupper(substr(get_the_title(), 0, 1));
            }

            if (!isset($groups[$letter])) $groups[$letter] = array();

            $groups[$letter][] = array(
                'title'        => get_the_title(),
                'content'      => apply_filters('the_content', get_the_content()),
                'video_url'    => get_post_meta($post_id, '_lexikon_video_url', true),
                'blog_post_id' => (int) get_post_meta($post_id, '_lexikon_blog_post_id', true),
                'file_url'     => get_post_meta($post_id, '_lexikon_file_url', true),
                'image_url'    => get_post_meta($post_id, '_lexikon_image_url', true),
            );
        }
        wp_reset_postdata();
    }

    ksort($groups);

    $output = '';
    foreach ($groups as $letter => $posts) {
        $output .= '<div class="alphabetsuche">' . "\n";
        $output .= '<strong>' . esc_html($letter) . '</strong>' . "\n";
        $output .= '<ul>' . "\n";

        foreach ($posts as $post) {
            $output .= '<li class="insvi"><div>';
            $output .= '<strong>' . esc_html($post['title']) . ':</strong> ' . $post['content'] . '</div>';

            $output .= lexikon_get_resources_html(
                $post['video_url'],
                $post['blog_post_id'],
                $post['file_url'],
                $post['image_url']
            );

            $output .= '</li>' . "\n";
        }

        $output .= '</ul>' . "\n";
        $output .= '</div>' . "\n";
    }

    return $output;
}
add_shortcode('lexikon_search', 'lexikon_search_shortcode');


/**
 * Gemeinsamer Renderer für die Lexikon-Suche (AJAX Inputs)
 *
 * Shortcodes:
 *  [verbraucher_search]
 *  [regel_search]
 *  [firmen_search]
 *  [lexikon_global_search]
 */
function lexikon_render_search_form($verfahren, $placeholder) {
    static $js_printed = false;

    $verfahren  = sanitize_key($verfahren);
    $action     = $verfahren . '_search';
    $nonce_name = $verfahren . '_search_nonce';
    $nonce      = wp_create_nonce($nonce_name);

    ob_start();
    ?>
    <div class="sidebar-search lexikon-search lexikon-search-<?php echo esc_attr($verfahren); ?>"
         data-action="<?php echo esc_attr($action); ?>"
         data-nonce="<?php echo esc_attr($nonce); ?>">
        <input
            type="text"
            class="sidebar-search-input lexikon-search-input"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            autocomplete="off"
        />
        <div class="search-results lexikon-search-results"></div>
    </div>
    <?php

    if ( ! $js_printed ) {
        $js_printed = true;
        ?>
        <script>
        (function() {
          const ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';

          // Lightbox für dynamisch geladene Suchergebnisse (innerHTML führt <script> nicht aus)
          function ensureLexikonLightbox() {
            let lb = document.getElementById('lexikon-lightbox');
            if (lb) {
              // Bestehende Lightbox ggf. aus verstecktem Container lösen
              if (lb.parentNode !== document.body) {
                document.body.appendChild(lb);
              }
              return lb;
            }

            lb = document.createElement('div');
            lb.id = 'lexikon-lightbox';
            lb.style.cssText = 'display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;';
            lb.innerHTML = '<button type="button" id="lexikon-lightbox-close" aria-label="Schließen" style="position:absolute;top:18px;right:24px;background:none;border:none;color:#fff;font-size:2.2rem;cursor:pointer;line-height:1;">&times;</button>' +
                           '<img id="lexikon-lightbox-img" src="" alt="Infografik" style="max-width:90vw;max-height:88vh;display:block;border-radius:6px;box-shadow:0 4px 40px rgba(0,0,0,.6);">';
            document.body.appendChild(lb);
            return lb;
          }

          function openLexikonLightbox(src) {
            if (!src) return;
            const lb = ensureLexikonLightbox();
            const img = lb.querySelector('#lexikon-lightbox-img');
            if (!img) return;
            img.src = src;
            lb.style.display = 'flex';
            lb.classList.add('is-open');
            document.body.style.overflow = 'hidden';
          }

          function closeLexikonLightbox() {
            const lb = document.getElementById('lexikon-lightbox');
            if (!lb) return;
            const img = lb.querySelector('#lexikon-lightbox-img');
            if (img) img.src = '';
            lb.style.display = 'none';
            lb.classList.remove('is-open');
            document.body.style.overflow = '';
          }

          document.addEventListener('click', function(e) {
            const imageBtn = e.target.closest('.lexikon-image-btn');
            if (imageBtn) {
              e.preventDefault();
              openLexikonLightbox(imageBtn.getAttribute('data-image'));
              return;
            }

            const lb = document.getElementById('lexikon-lightbox');
            if (!lb) return;
            const closeBtn = document.getElementById('lexikon-lightbox-close');

            if (e.target === lb || e.target === closeBtn) {
              closeLexikonLightbox();
            }
          });

          document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
              closeLexikonLightbox();
            }
          });

          function initLexikonSearch() {
            const containers = document.querySelectorAll('.lexikon-search');
            if (!containers.length) return;

            containers.forEach(container => {
              if (container.dataset.lexikonInit === '1') return;
              container.dataset.lexikonInit = '1';

              const input            = container.querySelector('.lexikon-search-input');
              const resultsContainer = container.querySelector('.lexikon-search-results');
              const action           = container.dataset.action;
              const nonce            = container.dataset.nonce;

              let searchTimeout;
              if (!input || !resultsContainer || !action || !nonce) return;

              input.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = this.value.trim();

                if (searchTerm.length < 2) {
                  resultsContainer.innerHTML = '';
                  return;
                }

                searchTimeout = setTimeout(() => {
                  performSearch(action, nonce, searchTerm, resultsContainer);
                }, 300);
              });
            });
          }

          function performSearch(action, nonce, term, resultsContainer) {
            resultsContainer.innerHTML = '<div class="search-loading">Suche läuft...</div>';

            fetch(ajaxUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({ action: action, search_term: term, nonce: nonce })
            })
            .then(r => r.json())
            .then(data => displayResults(resultsContainer, data))
            .catch(() => {
              resultsContainer.innerHTML = '<div class="search-error">Fehler bei der Suche</div>';
            });
          }

          function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, s => ({
              '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[s]));
          }

          function displayResults(resultsContainer, data) {
            if (!data.success || !Array.isArray(data.data) || data.data.length === 0) {
              resultsContainer.innerHTML = '<div class="no-results">Keine Ergebnisse gefunden</div>';
              return;
            }

            let html = '<div class="search-count">' + data.data.length + ' Ergebnis' + (data.data.length !== 1 ? 'se' : '') + '</div>';
            html += '<ul class="results-list">';

            data.data.forEach(item => {
              html += '<li class="result-item">';
              html += '<div class="result-title">' + escapeHtml(item.title) + '</div>';

              if (item.excerpt) {
                html += '<div class="result-excerpt">' + item.excerpt + '</div>';
              }

              if (item.resources_html) {
                html += item.resources_html;
              }

              html += '</li>';
            });

            html += '</ul>';
            resultsContainer.innerHTML = html;
          }

          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initLexikonSearch);
          } else {
            initLexikonSearch();
          }
        })();
        </script>
        <?php
    }

    return ob_get_clean();
}


/**
 * Shortcodes: Search Inputs
 */
function verbraucher_search_shortcode() {
    return lexikon_render_search_form('verbraucher', 'Verbraucherinsolvenz durchsuchen...');
}
add_shortcode('verbraucher_search', 'verbraucher_search_shortcode');

function regel_search_shortcode() {
    return lexikon_render_search_form('regel', 'Regelinsolvenz durchsuchen...');
}
add_shortcode('regel_search', 'regel_search_shortcode');

function firmen_search_shortcode() {
    return lexikon_render_search_form('firmen', 'Firmeninsolvenz durchsuchen...');
}
add_shortcode('firmen_search', 'firmen_search_shortcode');

function lexikon_global_search_shortcode() {
    return lexikon_render_search_form('lexikon', 'Gesamtes Lexikon durchsuchen...');
}
add_shortcode('lexikon_global_search', 'lexikon_global_search_shortcode');


/**
 * AJAX Handler (Verfahren)
 */
function verbraucher_search_ajax() {
    check_ajax_referer('verbraucher_search_nonce', 'nonce');
    $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
    if ($search_term === '') wp_send_json_success(array());
    wp_send_json_success( search_by_verfahren($search_term, 'verbraucher') );
}
add_action('wp_ajax_verbraucher_search', 'verbraucher_search_ajax');
add_action('wp_ajax_nopriv_verbraucher_search', 'verbraucher_search_ajax');

function regel_search_ajax() {
    check_ajax_referer('regel_search_nonce', 'nonce');
    $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
    if ($search_term === '') wp_send_json_success(array());
    wp_send_json_success( search_by_verfahren($search_term, 'regel') );
}
add_action('wp_ajax_regel_search', 'regel_search_ajax');
add_action('wp_ajax_nopriv_regel_search', 'regel_search_ajax');

function firmen_search_ajax() {
    check_ajax_referer('firmen_search_nonce', 'nonce');
    $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
    if ($search_term === '') wp_send_json_success(array());
    wp_send_json_success( search_by_verfahren($search_term, 'firmen') );
}
add_action('wp_ajax_firmen_search', 'firmen_search_ajax');
add_action('wp_ajax_nopriv_firmen_search', 'firmen_search_ajax');

function lexikon_search_ajax() {
    check_ajax_referer('lexikon_search_nonce', 'nonce');
    $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
    if ($search_term === '') wp_send_json_success(array());
    wp_send_json_success( search_by_verfahren($search_term, 'lexikon') );
}
add_action('wp_ajax_lexikon_search', 'lexikon_search_ajax');
add_action('wp_ajax_nopriv_lexikon_search', 'lexikon_search_ajax');


/**
 * Core search function (DB Query)
 */
if ( ! function_exists( 'search_by_verfahren' ) ) {
    function search_by_verfahren($search_term, $verfahren) {
        $search_term = sanitize_text_field($search_term);
        $verfahren   = sanitize_text_field($verfahren);

        $args = array(
            'post_type'      => 'lexikon',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            's'              => $search_term,
            'orderby'        => 'title',
            'order'          => 'ASC'
        );

        if ( ! empty($verfahren) && ! in_array($verfahren, array('lexikon', 'all'), true) ) {
            $args['meta_query'] = array(
                array(
                    'key'     => '_lexikon_tabs',
                    'value'   => $verfahren,
                    'compare' => 'LIKE'
                )
            );
        }

        $query   = new WP_Query($args);
        $results = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $post_id = get_the_ID();

                $content_full = apply_filters('the_content', get_post_field('post_content', $post_id));

                if (!empty($search_term)) {
                    $content_full = preg_replace(
                        '/' . preg_quote($search_term, '/') . '/i',
                        '<strong>$0</strong>',
                        $content_full
                    );
                }

                $video_url     = get_post_meta( $post_id, '_lexikon_video_url', true );
                $blog_post_id  = (int) get_post_meta( $post_id, '_lexikon_blog_post_id', true );
                $file_url      = get_post_meta( $post_id, '_lexikon_file_url', true );
                $image_url     = get_post_meta( $post_id, '_lexikon_image_url', true );

                $results[] = array(
                  'title'          => get_the_title($post_id),
                  'url'            => get_permalink($post_id),
                  'excerpt'        => $content_full,
                  'resources_html' => lexikon_get_resources_html($video_url, $blog_post_id, $file_url, $image_url),
                );
            }
            wp_reset_postdata();
        }

        return $results;
    }
}
