<?php
/**
 * Custom Fields
 *
 * @package Premia
 * @since 1.0
 */

namespace Premia;

/**
 * Github API Class
 * @since 1.0
 */
class Custom_Fields
{

    /**
     * Metabox ID.
     *
     * @var string The metabox id.
     */
    private $metabox_id = 'premia-settings';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Initializer.
     */
    public function init()
    {
        add_action('add_meta_boxes', array($this, 'add_metabox'));
        add_action('save_post', array($this, 'update_fields'), 20, 2);

        add_filter('premia_update_field', array($this, 'replace_github_url'));
        add_filter('premia_update_field', array($this, 'github_remove_last_slash'));
        add_filter('premia_update_field', array($this, 'do_not_validate'), 10, 3);
    }

    /**
     * Get fields
     *
     * @param string $type The type of fields to retrieve.
     */
    public static function get_fields($type = 'all')
    {
        $doc_generate_url = 'https://docs.github.com/en/github/authenticating-to-github/keeping-your-account-and-data-secure/creating-a-personal-access-token';
        $doc_bot_url = 'https://docs.github.com/en/github/getting-started-with-github/learning-about-github/types-of-github-accounts';

        $fields = array();

        $license_fields = apply_filters(
            'premia_customize_license_fields',
            array(
                array(
                    'name' => '_premia_linked_post_id',
                    'label' => __('Linked Item', 'premia'),
                    'desc' => __('Select a post that this license is linked to.', 'premia'),
                    'type' => 'select',
                    'visible' => true,
                ),
                array(
                    'name' => 'installations',
                    'label' => __('Active sites', 'premia'),
                    'desc' => __('Show enabled sites for this license', 'premia'),
                    'type' => 'link_list',
                    'visible' => true,
                ),
                array(
                    'name' => '_premia_expiry_date',
                    'label' => __('Expires on', 'premia'),
                    'type' => 'static_text',
                    'visible' => true,
                ),
            )
        );

        $post_fields = apply_filters(
            'premia_customize_post_fields',
            array(
                array(
                    'name' => '_updater_repo',
                    'label' => __('Github API URL', 'premia'),
                    'desc' => __('You can also paste the URL to your Github Repo', 'premia'),
                    'type' => 'text',
                    'visible' => true,
                ),
                array(
                    'name' => '_updater_api_token',
                    'type' => 'password',
                    'label' => __('Github API Key', 'premia'),
                    /* translators: %1$s, %2$s, %3$s, %4$s are all <a> and </a>'s */
                    'desc' => sprintf(__('%1$sCreating a personal access token%2$s - %3$sTypes of Github accounts%4$s.', 'premia'), '<a href="' . $doc_generate_url . '">', '</a>', '<a href="' . $doc_bot_url . '">', '</a>'),
                    'visible' => true,
                ),
                array(
                    'name' => '_updater_do_not_validate_licenses',
                    'type' => 'checkbox',
                    'label' => __('Do not validate licenses', 'premia'),
                    'desc' => __('When enabling this option, license checks are disabled.', 'premia'),
                    'visible' => true,
                ),
                array(
                    'name' => '_updater_nonce',
                    'type' => 'nonce',
                    'visible' => true,
                ),
            )
        );

        switch ($type) {
            case 'license':
                $fields = $license_fields;
                break;
            case 'post':
                $fields = $post_fields;
                break;
            default:
                $fields = array_merge($license_fields, $post_fields);
                break;
        }

        return $fields;
    }

    /**
     * Add metabox.
     */
    public function add_metabox()
    {
        add_meta_box($this->metabox_id, __('Premia settings', 'premia'), array($this, 'render_post_fields'), array('post', 'page'));
        add_meta_box($this->metabox_id, __('License settings', 'premia'), array($this, 'render_license_fields'), array('prem_license'));
    }

    /**
     * Render fields
     *
     * @param array $fields Array of fields to render.
     * @param object $post the related WP_Post.
     */
    public function render_fields($fields, $post)
    {
        foreach ($fields as $field) {
            if (true === $field['visible']) {
                echo '<div>';
                switch ($field['type']) {
                    case 'checkbox':
                        $checked = (get_post_meta($post->ID, $field['name'], true) === 'on' ? ' checked="checked"' : '');
                        echo '<label for="' . esc_attr($field['name']) . '">';
                        echo '<input id="' . esc_attr($field['name']) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr($field['name']) . '" ' . esc_html($checked) . ' />';
                        echo esc_html($field['label']);
                        echo '</label>';
                        break;
                    case 'select':
                        echo '<label for="' . esc_attr($field['name']) . '"><strong>' . esc_html($field['label']) . '</strong></label><br/>';
                        $choices = get_posts(
                            array(
                                'post_type' => apply_filters('premia_supported_post_types', array('post', 'page')),
                                'numberposts' => -1,
                            )
                        );
                        echo '<select id="' . esc_attr($field['name']) . '" name="' . esc_attr($field['name']) . '">';
                        foreach ($choices as $choice) {
                            $selected = (intval(get_post_meta($post->ID, $field['name'], true)) === $choice->ID ? ' selected="selected"' : '');
                            echo '<option name="' . esc_attr($choice->ID) . '" value="' . esc_attr($choice->ID) . '" ' . esc_html($selected) . '>' . esc_html($choice->post_title) . '</option>';
                        }
                        echo '</select>';
                        echo '<div><i>' . esc_html($field['desc']) . '</i></div>';
                        break;
                    case 'post_link':
                        echo '<label for="' . esc_attr($field['name']) . '"><strong>' . esc_html($field['label']) . '</strong></label><br/>';
                        $value = get_post_meta($post->ID, $field['name'], true);
                        if (!empty($value)) {
                            $post = get_post($value);
                            if (!is_wp_error($post)) {
                                echo '<a class="button button-secondary" href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html($post->post_title) . '</a>';
                            }
                        }
                        break;
                    case 'order_link':
                        echo '<label for="' . esc_attr($field['name']) . '"><strong>' . esc_html($field['label']) . '</strong></label><br/>';
                        $value = get_post_meta($post->ID, $field['name'], true);
                        if (!empty($value)) {
                            $order = wc_get_order($value);
                            $title = 'Order #' . $value;
                            if (!is_wp_error($order)) {
                                echo '<a class="button button-secondary" href="' . esc_url($order->get_edit_order_url()) . '">' . esc_html($title) . '</a>';
                            }
                        }
                        break;
                    case 'link_list':
                        echo '<label for="' . esc_attr($field['name']) . '"><strong>' . esc_html($field['label']) . '</strong></label><br/>';
                        $value = get_post_meta($post->ID, $field['name'], true);
                        if (is_array($value) && !empty($value)) {
                            echo '<ul>';
                            foreach ($value as $site) {
                                echo '<li><a href="' . esc_html($site) . '">' . esc_html($site) . '</a></li>';
                            }
                            echo '</ul>';
                        }
                        break;
                    case 'static_text':
                        echo '<label for="' . esc_attr($field['name']) . '"><strong>' . esc_html($field['label']) . '</strong></label><br/>';
                        $value = get_post_meta($post->ID, $field['name'], true);
                        if (!empty($value)) {
                            $date = new \Datetime();
                            $date->setTimestamp($value);
                            echo esc_html($date->format(get_option('date_format') . ' ' . get_option('time_format')));
                        }
                        break;
                    case 'nonce':
                        wp_nonce_field(-1, esc_attr($field['name']));
                        break;
                    default:
                        echo '<label for="' . esc_attr($field['name']) . '">' . esc_attr($field['label']) . '</label><br/>';
                        echo '<input id="' . esc_attr($field['name']) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr($field['name']) . '" value="' . esc_html(get_post_meta($post->ID, $field['name'], true)) . '" />';
                        echo '<div><i>' . wp_kses_post($field['desc']) . '</i></div>';
                        break;
                }
                echo '</div><br/>';
            }
        }
    }

    /**
     * Render post fields
     *
     * @param object $post WP_Post.
     */
    public function render_post_fields($post)
    {
        $fields = $this->get_fields('post');
        return $this->render_fields($fields, $post);
    }

    /**
     * Render license fields
     *
     * @param object $post WP_Post.
     */
    public function render_license_fields($post)
    {
        $fields = $this->get_fields('license');
        return $this->render_fields($fields, $post);
    }

    /**
     * Update fields
     *
     * @param int $post_id The Post ID.
     * @param object $post A WP_Post object.
     */
    public function update_fields($post_id, $post)
    {

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ('auto-draft' === $post->post_status) {
            return;
        }

        $fields = $this->get_fields();

        if (isset($_POST['_updater_nonce']) && wp_verify_nonce(sanitize_key($_POST['_updater_nonce']))) {
            foreach ($fields as $field) {
                $value = '';
                if (isset($_POST[$field['name']])) {
                    $value = sanitize_text_field(wp_unslash($_POST[$field['name']]));
                    $value = apply_filters('premia_update_field', $value, $field, $post_id);
                    update_post_meta($post_id, $field['name'], $value);
                } else {
                    delete_post_meta($post_id, $field['name']);
                }
            }
        }
    }

    /**
     * Replaces the Github Repo URL to an API one.
     *
     * @param string $value User submitted.
     * @return string The new value.
     */
    public static function replace_github_url($value)
    {
        if (strpos($value, 'github.com') !== false && strpos($value, 'api.github.com') === false) {
            $value = rtrim(sanitize_text_field($value), '/');
            $value = str_replace('github.com', 'api.github.com/repos', $value);
        }
        return $value;
    }

    /**
     * Removes the last slash in an URL.
     *
     * @param string $value The value.
     * @return string The new value.
     */
    public static function github_remove_last_slash($value)
    {
        if (strpos($value, 'github.com') !== false) {
            $value = rtrim(sanitize_text_field($value), '/');
        }
        return $value;
    }

    /**
     * When the checkbox for validation is selected, save the value as "on".
     *
     * @param string $value The current value.
     * @param array $field the CMB2 field.
     * @param int $post_id The Post ID.
     * @return string The new value.
     */
    public function do_not_validate($value, $field, $post_id)
    {
        // We don't need to do this for Woocommerce products.
        if (get_post_type($post_id) !== 'product') {
            if ('_updater_do_not_validate_licenses' === $field['name']) {
                $value = 'on';
            }
        }
        return $value;
    }
}
