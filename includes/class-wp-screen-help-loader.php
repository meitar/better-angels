<?php
/**
 * Manages WordPress on-screen help tabs and sidebar content based on
 * a directory of markdown files.
 *
 * @author maymay <bitetheappleback@gmail.com>
 * @copyright Copyright (c) 2015-2016 by Meitar "maymay" Moscovitz
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 * @package WordPress\Plugin\WP_Buoy_Plugin\WP_Screen_Help_Loader
 */

if (!defined('ABSPATH')) { exit; } // Disallow direct HTTP access.

class WP_Screen_Help_Loader {

    /**
     * The current screen.
     *
     * @var WP_Screen
     */
    private $_screen;

    /**
     * An instance of the Parsedown markdown parser.
     *
     * @see http://parsedown.org/ The Parsedown parser's homepage.
     *
     * @var Parsedown
     */
    private $_parsedown;

    /**
     * Base path to the help folder contents.
     *
     * @var string
     */
    private $_help_dir_path;

    /**
     * Filepaths containing help tab(s) contents.
     *
     * @var string[]
     */
    private $_tab_files;

    /**
     * Filepaths containing help sidebar contents.
     *
     * @var string[]
     */
    private $_sidebar_files;

    /**
     * Constructor.
     *
     * @param string $path Base path from which to load files.
     *
     * @uses WP_Screen_Help_Loader::get_help_dir_path()
     * @uses get_current_screen()
     *
     * @return void
     */
    public function __construct ($path) {
        $this->_help_dir_path = $this->get_help_dir_path($path);
        $this->_screen        = get_current_screen();
        $this->_tab_files     = $this->get_help_tab_files();
        $this->_sidebar_files = $this->get_help_sidebar_files();
    }

    /**
     * Get the directory containing localized help tab contents.
     *
     * @uses trailingslashit()
     * @uses get_locale()
     *
     * @param string $path Relative path base. Defaults to `__FILE__`.
     *
     * @return string
     */
    public function get_help_dir_path ($path = __FILE__) {
        return trailingslashit(trailingslashit($path) . get_locale());
    }

    /**
     * Finds the appropriate help files.
     *
     * @todo Allow for file types/extensions other than markdown.
     *
     * @return string[]
     */
    public function get_help_tab_files () {
        return glob($this->_help_dir_path . "{$this->_screen->action}{$this->_screen->id}*.md");
    }

    /**
     * Finds the appropriate help sidebar files.
     *
     * @todo Allow for file types/extensions other than markdown.
     *
     * @return string[]
     */
    public function get_help_sidebar_files () {
        return array(
            $this->_help_dir_path . "sidebar-{$this->_screen->id}.md", // screen-specific sidebar
            $this->_help_dir_path . 'sidebar.md'                       // global sidebar (footer)
        );
    }

    /**
     * Applies the new sidebar contents based on loaded files.
     *
     * This should be called during WordPress's `load-{$pagenow}` hook.
     *
     * @see https://developer.wordpress.org/reference/hooks/load-pagenow/
     *
     * @todo Allow for file types/extensions other than markdown.
     *
     * @uses Parsedown::text()
     * @uses WP_Screen::add_help_tab()
     *
     * @return @void
     */
    public function applyTabs () {
        $num = 1;
        foreach ($this->_tab_files as $file) {
            $lines = (is_readable($file)) ? file($file) : false;
            if ($lines) {
                $args = array(
                    'title' => sanitize_text_field($this->get_parsedown()->text(array_shift($lines))),
                    'id' => esc_attr("{$this->_screen->id}-help-tab-$num"),
                    'content' => $this->get_parsedown()->text(implode("\n", $lines))
                );
                preg_match('/-([0-9]+)\.md$/i', $file, $m);
                if ($m) {
                    $args['priority'] = absint($m[1]);
                }
                $this->_screen->add_help_tab($args);
            }
            $num++;
        }
    }

    /**
     * Applies the new sidebar contents based on loaded files.
     *
     * This should be called during WordPress's `admin_head` hook.
     *
     * @see https://developer.wordpress.org/reference/hooks/admin_head/
     *
     * @uses Parsedown::text()
     * @uses WP_Screen::get_help_sidebar()
     * @uses WP_Screen::set_help_sidebar()
     *
     * @return @void
     */
    public function applySidebar () {
        foreach ($this->_sidebar_files as $file) {
            if (is_readable($file)) {
                $this->_screen->set_help_sidebar(
                    $this->_screen->get_help_sidebar()
                    . // append
                    $this->get_parsedown()->text(file_get_contents($file))
                );
            }
        }
    }

    /**
     * Gets a Parsedown instance.
     *
     * @return Parsedown
     */
    private function get_parsedown () {
        if (null === $this->_parsedown) {
            if (!class_exists('Parsedown')) {
                require_once plugin_dir_path(__FILE__) . 'vendor/parsedown/Parsedown.php';
            }
            $this->_parsedown = new Parsedown();
        }
        return $this->_parsedown;
    }

}
