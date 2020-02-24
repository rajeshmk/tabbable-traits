<?php

namespace Voco\Http\Traits;

use Illuminate\Support\Str;

trait AjaxTabbableTabs
{
    private $tab_meta_data = [];
    private $route_params = [];
    private $tab_buttons = [];
    private $tab_class_name = '';
    private $tab_namespace = '';
    private $tab_prefix = 'Tab';
    private $selected_tab = null;
    protected $not_found_tab_blade = '404-not-found';
    protected $activate_via_js = false;

    protected function initTabPaths()
    {
        $parts = explode('\\', get_called_class());

        $this->tab_class_name = array_pop($parts);
        array_pop($parts);

        $this->tab_namespace = implode('\\', $parts);
    }

    protected function getBladeRelativePath()
    {
        $namespace_parts = preg_split('/\\\\/', str_replace('App\Http\Controllers', '', $this->tab_namespace), -1, PREG_SPLIT_NO_EMPTY);

        array_walk($namespace_parts, function(&$item) {
            $item = Str::kebab($item);
        });

        return implode('/', $namespace_parts);
    }

    protected function dottedBlade($blade_path)
    {
        return str_replace('/', '.', $blade_path);
    }

    /**
     *
     * @param string $param parameter name
     * @param string|int $value parameter value
     */
    protected function addRouteParam($param, $value)
    {
        $this->route_params[$param] = $value;
    }

    /**
     * General function to create meta data for ajax driven tabs
     *
     * @param string $tab tab name to be shown in url
     * @param string $icon tab icon class
     */
    protected function addTabs($tab, $icon = false, $has_form = false)
    {
        $snake_case = Str::snake(Str::studly($tab));
        $tab = str_replace('_', '-', $snake_case);

        // add tab name to existing route parameters
        $this->route_params['tab_name'] = $tab;

        // tab name snake case
        $this->tab_meta_data[$tab]['tab_snake'] = $snake_case;

        // prepare tab url from route
        $this->tab_meta_data[$tab]['tab_url'] = route($this->route_name, $this->route_params);

        // provide tab icon, if any
        $this->tab_meta_data[$tab]['tab_icon'] = $icon ? '<i class="' . $icon . '"></i>' : '';

        $this->tab_meta_data[$tab]['has_form'] = $has_form;

        // set tab content, if not already set by "setTabContent()"
        if (!isset($this->tab_meta_data[$tab]['tab_content'])) {
            $this->tab_meta_data[$tab]['tab_content'] = '';
        }

        $this->tab_meta_data[$tab]['tab_nav_id'] = 'vtab-' . $tab;
        $this->tab_meta_data[$tab]['tab_content_id'] = 'vcontent-' . $tab;
    }

    public function getTabs()
    {
        return $this->tab_meta_data;
    }

    public function setSelectedTab($tab)
    {
        $this->selected_tab = $tab;
    }

    public function getSelectedTab($default = null)
    {
        // return selected tab
        if ($this->selected_tab && isset($this->tab_meta_data[$this->selected_tab])) {
            return $this->selected_tab;
        }

        // return default tab
        if (isset($this->tab_meta_data[$default])) {
            return $default;
        }

        // return first tab (fallback)
        return current(array_keys($this->tab_meta_data));
    }

    protected function setTabContent($tab, $content)
    {
        $this->tab_meta_data[$tab]['tab_content'] = $content;
    }

    /**
     *
     * @param string $tag HTML tag name eg. 'a', 'div' etc.
     * @param string $text label for the button
     * @param string $icon icon class
     * @param array $attributes array of DOM attributes
     */
    protected function addTabButton($tag, $text = '', $icon = '', $attributes = [])
    {
        $attr_string = '';
        foreach ($attributes as $name => $value) {
            $attr_string .= ' ' . $name . '="' . $value . '"';
        }

        $this->tab_buttons[] = '<' . $tag . $attr_string . '>'
                . (empty($icon) ? '' : ' <i class="' . $icon . '"></i>')
                . (empty($text) ? '' : ' ' . $text)
                . '</' . $tag . '>' . PHP_EOL;
    }

    protected function activateViaJs($status = true)
    {
        $this->activate_via_js = (bool) $status;
    }

    protected function tabViewNotFound()
    {
        // prepare blade relative path from namespace
        $blade_storage = $this->getBladeRelativePath();

        $view = $this->dottedBlade($blade_storage . '/' . $this->not_found_tab_blade);

        // auto generate not found file when not in production environment
        // @TODO - move to general place
        if ('production' !== config('app.env')) {
            $this->_createNotFoundBladeFile($blade_storage);
        }

        return view($view);
    }

    protected function tabView($tab_name)
    {
        foreach ($this->tab_meta_data as $tab => $tab_info) {
            $nav_link_attribs = [
                'data-toggle="tab"',
                'role="tab"',
                'id="' . $tab_info['tab_nav_id'] . '"',
                'href="#' . $tab_info['tab_content_id'] . '"',
                'data-url="' . $tab_info['tab_url'] . '"',
                'aria-controls="' . $tab_info['tab_content_id'] . '"',
                // setting associative property for update in next step
                'class' => 'class="nav-link"',
                'aria-selected' => 'aria-selected="false"',
            ];

            $tab_pane_attribs = [
                'id="' . $tab_info['tab_content_id'] . '"',
                'role="tabpanel"',
                'aria-labelledby="' . $tab_info['tab_nav_id'] . '"',
                // setting associative property for update in next step
                'class' => 'class="tab-pane fade"',
            ];

            if ($tab_name === $tab && $this->activate_via_js !== true) {

                // append class "active" to enable current tab - CSS class for active tab
                $nav_link_attribs['class'] = 'class="nav-link active"';
                $nav_link_attribs['aria-selected'] = 'aria-selected="true"';

                // append class "show active"
                $tab_pane_attribs['class'] = 'class="tab-pane fade show active"';
            }

            $this->tab_meta_data[$tab]['nav_tab_item_html'] = '<a '
                    . implode(' ', $nav_link_attribs) . '>'
                    . $tab_info['tab_icon'] . ' ' . str_replace('-', ' ', ucwords($tab, '-'))
                    . '</a>';

            $this->tab_meta_data[$tab]['tab_pane_html'] = '<div '
                    . implode(' ', $tab_pane_attribs)
                    . '>' . $tab_info['tab_content'] . '</div>';
        }

        $data = [
            'tab_name' => $tab_name,
            'tab_meta_data' => $this->tab_meta_data,
            'tab_buttons' => $this->tab_buttons,
            'activate_via_js' => $this->activate_via_js,
        ];

        // finally return tab data
        return $data;
    }

    protected function baseNamespace($namespace)
    {
        $parts = explode('\\', $namespace);
        return end($parts);
    }

    protected function renderView($module, $namespace, $tab_name, $data)
    {
        // be careful! dynamic controller used for direct invoking of controller
        $view = \App::call('App\Http\Controllers\\'
                        . $module . '\\'
                        . $namespace . '\AjaxTabs\Tab'
                        . Str::studly($tab_name) . 'Controller@index'
                        , $data
        );

        if (empty($view)) {
            return '';
        }

        return ($view instanceof \Illuminate\View\View) ? $view->render() : $view;
    }

}
