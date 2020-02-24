<?php

namespace Voco\Http\Traits;

use Illuminate\Support\Str;

trait AjaxTabbableDevelopmentEnv
{
    private $generated_files = [];

    /* dependency injection */
    private $custome_di_classes = [];

    protected function ensureFiles($path)
    {
        // do not auto generate files in production environment
        if ('production' === config('app.env')) {
            return false;
        }

        // prepare blade relative path from namespace
        $blade_storage = $this->getBladeRelativePath();

        foreach ($this->tab_meta_data as $tab_name => $tab_meta) {

            $tab_studly_case = Str::studly($tab_name);
            $class_name = $this->tab_prefix . $tab_studly_case . 'Controller';

            $controller_filename = $path . '/' . $class_name . '.php';
            $blade_related_path = $blade_storage . '/' . Str::kebab($this->tab_prefix . $tab_studly_case);

            // prepare content for controller file
            $this->_createControllerClass($controller_filename, $blade_related_path, $class_name, $tab_meta);

            // prepare content for blade file
            $this->_createBladeFile($blade_related_path);
        }

        // prepare content for NOT FOUND blade file
        $this->_createNotFoundBladeFile($blade_storage);

        $message_parts = [];
        if (isset($this->generated_files['controllers'])) {
            $message_parts[] = count($this->generated_files['controllers']) . ' controllers';
        }
        if (isset($this->generated_files['views'])) {
            $message_parts[] = count($this->generated_files['views']) . ' views';
        }

        // @TODO - replace session based flash data, with single request message
        if (count($message_parts) > 0) {
            voco_alert()->info(implode(' and ', $message_parts) . ' auto generated!');
        }
    }

    protected function addDependencyInjection($namespace_path, $variable_name)
    {
        $this->custome_di_classes[$namespace_path] = $variable_name;
    }

    private function _createControllerClass($file, $blade_related_path, $class_name, $tab_meta)
    {
        if (file_exists($file)) {
            return false;
        }

        // default params
        $params_formatted = ['Request $request'];

        $custom_use_for_di = [];
        foreach ($this->custome_di_classes as $di_class => $di_var) {
            $custom_use_for_di[] = $di_class;
            $params_formatted[] = $this->baseNamespace($di_class) . ' $' . $di_var;
        }

        $route_params_form_checking = [];
        foreach (array_keys($this->route_params) as $param_name) {
            $params_formatted[] = '$' . $param_name;

            if ($param_name !== 'tab_name') {
                $route_params_form_checking[] = '$request->' . $param_name . ' != $' . $param_name;
            }
        }

        $blade_dotted = $this->dottedBlade($blade_related_path);

        $function_save = '';
        if ($tab_meta['has_form'] === true) {
            $function_save .= '    public function save(' . implode(', ', $params_formatted) . ')' . PHP_EOL
                    . '    {' . PHP_EOL;

            if ($route_params_form_checking) {
                $function_save .= '        if (' . implode(' || ', $route_params_form_checking) . ') {' . PHP_EOL
                        . '            throw new \Exception(\'You are redirected from an invalid link\');' . PHP_EOL
                        . '        }' . PHP_EOL;
            }
            $function_save .= PHP_EOL
                    . '        // save form data here' . PHP_EOL
                    . '    }' . PHP_EOL . PHP_EOL;
        }

        $file_contents = '<?php

namespace ' . $this->tab_namespace . ';

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;' . PHP_EOL;

        foreach ($custom_use_for_di as $use) {
            $file_contents .= 'use ' . $use . ';' . PHP_EOL;
        }

        $file_contents .= 'use Validator;
use Auth;

class ' . $class_name . ' extends Controller
{
    public function index(' . implode(', ', $params_formatted) . ')
    {
        return view(\'' . $blade_dotted . '\');
    }' . PHP_EOL . PHP_EOL
                . $function_save
                . '}' . PHP_EOL;

        // save controller file
        file_put_contents($file, $file_contents);

        $this->generated_files['controllers'][] = basename($file);
    }

    private function _createNotFoundBladeFile($blade_related_path)
    {
        $file = resource_path('views/' . $blade_related_path
                . '/' . $this->not_found_tab_blade . '.blade.php');

        if (file_exists($file)) {
            return false;
        }

        // create directory tree, if not already exists
        $this->_ensurePath(dirname($file));

        $file_basename = basename($file);

        $file_contents = '{{-- ' . $file_basename . ' --}}' . PHP_EOL . PHP_EOL
                . '@extends(\'layouts.app\')' . PHP_EOL . PHP_EOL
                . '@section(\'content\')' . PHP_EOL . PHP_EOL
                . '<h5>' . PHP_EOL
                . '  <div class="alert alert-danger">Not Found!</div>' . PHP_EOL
                . '</h5>' . PHP_EOL
                . '@endsection' . PHP_EOL;

        // save blade file
        file_put_contents($file, $file_contents);

        $this->generated_files['views'][] = $file_basename;
    }

    private function _createBladeFile($blade_related_path)
    {
        $file = resource_path('views/' . $blade_related_path . '.blade.php');

        if (file_exists($file)) {
            return false;
        }

        // create directory tree, if not already exists
        $this->_ensurePath(dirname($file));

        $file_basename = basename($file);

        $file_contents = '{{-- ' . $file_basename . ' --}}' . PHP_EOL . PHP_EOL
                . '<h5>'
                . ucwords(str_replace('-', ' ', basename($blade_related_path)))
                . '</h5>' . PHP_EOL;

        // save blade file
        file_put_contents($file, $file_contents);

        $this->generated_files['views'][] = $file_basename;
    }

    private function _ensurePath($path)
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }

}
