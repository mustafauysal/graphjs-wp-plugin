<?php

namespace Graphjs;

class WordpressPlugin
{
    const GRAPHJS_UUID = 'graphjs_uuid';
    const GRAPHJS_THEME = 'graphjs_theme';
    const GRAPHJS_COLOR = 'graphjs_color';
    const GRAPHJS_OVERRIDE_COMMENT = 'graphjs_override_comment';

    const GRAPHJS_DEFAULT_THEME = "light";
    const GRAPHJS_DEFAULT_OVERRIDE_COMMENT = false;

    private $pluginFile;
    private $pluginDirectory;
    private $graphjs;

    /**
     * @param string $pluginFile
     * @param string $pluginDirectory
     * @param Graphjs $graphjs
     */
    public function __construct($pluginFile, $pluginDirectory, Graphjs $graphjs)
    {
        $this->pluginFile = $pluginFile;
        $this->pluginDirectory = $pluginDirectory;
        $this->graphjs = $graphjs;
    }

    public function bootstrap()
    {
        $this->registerActivationHook();
        $this->registerDeactivationHook();
        $this->registerUninstallHook();

        $this->registerShortcodes();
        $this->registerActions();
        $this->registerFilters();
    }

    public function initMetabox()
    {
        add_action('add_meta_boxes', [ $this, 'addMetabox' ]);
        add_action('save_post', [ $this, 'saveMetabox' ], 10, 2);
    }

    public function addMetabox($postType)
    {
        $supportedPostTypes = [ 'post', 'page' ];

        if (in_array($postType, $supportedPostTypes)) {
            add_meta_box(
                'graphjs-admin-content-restriction',
                'GraphJS Content Restriction',
                [$this, 'renderMetabox'],
                $postType,
                'advanced',
                'default'
            );
        }
    }

    public function renderMetabox(\WP_Post $post)
    {
        $graphjsContentRestriction = get_post_meta($post->ID, 'graphjs_restrict_content', true);
        $contentRestriction = boolval($graphjsContentRestriction);

        $graphjsId = get_post_meta($post->ID, 'graphjs_id', true);

        include $this->pluginDirectory . '/view/content_restriction_metabox.php';
    }

    public function saveMetabox($postId, \WP_Post $post)
    {
        /**
         * If this is an autosave, our form has not been submitted,
         * so we don't want to do anything.
         */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $postId;
        }

        $contentRestriction = $_POST['graphjs_content_restriction_status'];
        $isRestricted = ($contentRestriction === 'on');
        update_post_meta($postId, 'graphjs_restrict_content', $isRestricted);

        if ($isRestricted) {
            $id = $_POST['graphjs_content_restriction_id'];
            if ($id) {
                update_post_meta($postId, 'graphjs_id', $id);
            }
        }
    }

    public function registerActivationHook()
    {
        register_activation_hook($this->pluginFile, [ $this, 'activate' ]);
    }

    public function registerDeactivationHook()
    {
        register_deactivation_hook($this->pluginFile, [ $this, 'deactivate' ]);
    }

    public function registerUninstallHook()
    {
        register_uninstall_hook($this->pluginFile, [ __CLASS__, 'uninstall' ]);
    }

    public function activate()
    {
        $uuid = get_option(self::GRAPHJS_UUID);
        if ($uuid === false) {
            add_option(self::GRAPHJS_UUID, "");
        }

        $theme = get_option(self::GRAPHJS_THEME);
        if ($theme === false) {
            add_option(self::GRAPHJS_THEME, self::GRAPHJS_DEFAULT_THEME);
        }

        $color = get_option(self::GRAPHJS_COLOR);
        if ($color === false) {
            add_option(self::GRAPHJS_COLOR, "");
        }

        $overrideComment = get_option(self::GRAPHJS_OVERRIDE_COMMENT);
        if ($overrideComment === false) {
            add_option(self::GRAPHJS_OVERRIDE_COMMENT, self::GRAPHJS_DEFAULT_OVERRIDE_COMMENT);
        }
    }

    public function deactivate()
    {

    }

    public static function uninstall()
    {
        delete_option(self::GRAPHJS_UUID);
        delete_option(self::GRAPHJS_THEME);
        delete_option(self::GRAPHJS_COLOR);
        delete_option(self::GRAPHJS_OVERRIDE_COMMENT);
    }

    public function registerShortcodes()
    {
        $elements = $this->graphjs->getElements();
        array_walk($elements, function ($element) {
            $shortcodeRenderer = new ShortcodeRenderer($element);
            add_shortcode($element, [ $shortcodeRenderer, 'render' ]);
        });
    }

    public function registerActions()
    {
        add_action('admin_init', function () {

            $this->addActionLinks();
            $this->registerSettings();
        });

        add_action('admin_menu', function () {
            $this->addAdminMenuPage();
        });

        add_action('wp_footer', [ $this, 'my_custom_admin_head' ]);

        if (is_admin()) {
            add_action('load-post.php', [ $this, 'initMetabox' ]);
            add_action('load-post-new.php', [ $this, 'initMetabox' ]);

            $callback = function () {
                add_action('admin_footer', [ $this, 'my_custom_admin_head' ]);
            };

            add_action('load-post.php', $callback);
            add_action('load-post-new.php', $callback);
        }

        add_action('admin_footer', [ $this, 'my_custom_admin_head' ]);

        add_action('admin_enqueue_scripts', [ $this, 'enqueueAdminScript' ]);
    }

    public function enqueueAdminScript($hook)
    {
        $args = func_get_args();
        global $post;

        if (in_array($hook, [ 'post-new.php', 'post.php' ])) {
            if (in_array($post->post_type, [ 'post', 'page' ])) {
                wp_enqueue_script('graphjs-post-submit', plugin_dir_url($this->pluginFile) . 'js/post_submit.js', 'jquery');
            }
        }
    }

    public function my_custom_admin_head()
    {
        $path = $this->pluginDirectory . '/view/init.php';
        include $path;
    }

    public function addAdminMenuPage()
    {
        $graphjs_main_menu_page = function () {
            $elements = $this->graphjs->getElements();
            $path = $this->pluginDirectory . '/view/graphjs.php';
            include $path;
        };

        add_menu_page('GraphJS', 'GraphJS',
            'administrator', 'graphjs',
            $graphjs_main_menu_page, 'dashicons-admin-generic');

        $graphjs_settings_menu_page = function () {
            $path = $this->pluginDirectory . '/view/setting_view.php';
            include $path;
        };

        add_submenu_page('graphjs', 'Tutorial', 'Tutorial',
            'administrator', 'graphjs',
            $graphjs_main_menu_page);

        add_submenu_page('graphjs', 'GraphJS Settings', 'Settings',
            'administrator', 'graphjs-settings',
            $graphjs_settings_menu_page);
    }

    public function addActionLinks()
    {
        // Add setting link
        $plugin_file = plugin_basename($this->pluginFile);
        $fn = function ($actions) {
            $actions['settings'] = '<a href="' . menu_page_url('graphjs-settings', false) . '"/>' . __('Settings') . '</a>';
            return $actions;
        };
        add_filter("plugin_action_links_$plugin_file", $fn);
    }

    public function registerFilters()
    {
        add_filter('comments_template', function () {
            return $this->getCommentTemplate();
        });
    }

    public function getCommentTemplate()
    {
        if ($this->overrideCommentTemplate()) {
            return plugin_dir_path( dirname( __FILE__ ) ) . 'view/comment.php';
        }
    }

    public function overrideCommentTemplate()
    {
        return boolval(get_option(self::GRAPHJS_OVERRIDE_COMMENT));
    }

    public function registerSettings()
    {
        // Register allowed form fields of setting
        register_setting('graphjs_options', self::GRAPHJS_UUID, 'strval');
        register_setting('graphjs_options', self::GRAPHJS_THEME, 'strval');
        register_setting('graphjs_options', self::GRAPHJS_COLOR, 'strval');
        register_setting('graphjs_options', self::GRAPHJS_OVERRIDE_COMMENT, 'boolval');
    }
}
