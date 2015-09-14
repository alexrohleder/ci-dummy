<?php 

/**
 * Codeburner Framework.
 *
 * @author Alex Rohleder <contato@alexrohleder.com.br>
 * @copyright 2015 Alex Rohleder
 * @license http://opensource.org/licenses/MIT
 */

namespace Codeburner\Router;

use Exception;
use ReflectionMethod;
use ReflectionParameter;
use Codeburner\Router\BadRouteException;

/**
 * An empty interface, it is used to identify a class as a mapper extension definition.
 *
 * @author Alex Rohleder <contato@alexrohleder.com.br>
 * @since 1.0.0
 */

interface MapperExtensionInterface
{
    // empty ...
}

/**
 * The mapper class is reponsable to hold all the defined routes and give then
 * in a organized form focused to reduce the search time.
 * 
 * @author Alex Rohleder <contato@alexrohleder.com.br>
 * @since 1.0.0
 */

class Mapper
{

    use HttpMethodMapper;
    use ResourceMapper;
    use ControllerMapper;

    const DINAMIC_REGEX = '\{\s*([\w]*)\s*(?::\s*([^{}]*(?:\{(?-1)\}[^{}]*)*))?\s*\}';
    const DEFAULT_PLACEHOLD_REGEX = '([^/]+)';

    public static $supported_http_methods = [
        'get', 
        'post', 
        'put', 
        'patch', 
        'delete'
    ];
    
    public static $pattern_wildcards = [
        'int' => '\d+',
        'integer' => '\d+',
        'string' => '\w+',
        'float' => '[-+]?(\d*[.])?\d+',
        'bool' => '^(1|0|true|false|yes|no)$',
        'boolean' => '^(1|0|true|false|yes|no)$'
    ];
    
    public static $action_separator = '#';

    protected $statics  = [];
    protected $dinamics = [];

    public function set($method, $pattern, $action)
    {
        $method = $this->parseHttpMethod($method);
        $patterns = $this->parsePatternOptionals($pattern);
        $action = $this->parseRouteAction($action);

        foreach ($patterns as $pattern) {
            if (strpos($pattern, '{') === false) {

                $this->statics[$method][$pattern]  = ['action' => $action, 'params' => []];
            
            } else {

                $offset = $this->getPatternOffset();
                $this->dinamics[$method][$offset][$pattern] = ['action' => $action, 'params' => []];

            }
        }
    }

    protected function parseHttpMethod($method)
    {
        if (!in_array($method = strtolower($method), self::$supported_http_methods)) {
            throw new BadRouteException(BadRouteException::UNSUPPORTED_HTTP_METHOD);
        }

        return $method;
    }

    protected function parseRouteAction($action)
    {
        if (is_string($action)) {
            return explode(self::$action_separator, $action);
        }

        return $action;
    }

    protected function parsePatternOptionals($pattern)
    {
        $patternOptionalsNumber  = substr_count($pattern, ']');
        $patternWithoutClosingOptionals = rtrim($pattern, ']');

        $segments = preg_split('~' . self::DINAMIC_REGEX . '(*SKIP)(*F) | \[~x', $patternWithoutClosingOptionals);
        $this->checkSegmentsOptionals($segments, $patternOptionalsNumber, $patternWithoutClosingOptionals);

        return $this->buildPatternSegments($segments);
    }

    public function getPatternOffset($pattern)
    {
        return substr_count($pattern, '/') - 1;
    }

    protected function checkSegmentsOptionals($segments, $patternOptionalsNumber, $patternWithoutClosingOptionals)
    {
        if ($patternOptionalsNumber !== count($segments) - 1) {
            if (preg_match('~' . self::DINAMIC_REGEX . '(*SKIP)(*F) | \]~x', $patternWithoutClosingOptionals)) {
                   throw new BadRouteException(BadRouteException::OPTIONAL_SEGMENTS_ON_MIDDLE);
            } else throw new BadRouteException(BadRouteException::UNCLOSED_OPTIONAL_SEGMENTS);
        }
    }

    protected function buildPatternSegments($segments)
    {
        $pattern  = '';
        $patterns = [];

        foreach ($segments as $n => $segment) {
            if ($segment === '' && $n !== 0) {
                throw new BadRouteException(BadRouteException::EMPTY_OPTIONAL_PARTS);
            }

            $patterns[] = $pattern .= $segment;
        }

        return $patterns;
    }

    protected function parsePatternPlaceholders($pattern)
    {
        $parameters = [];
        preg_match_all('~' . self::DINAMIC_REGEX . '~x', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ((array) $matches as $match) {
            $pattern = str_replace($match[0][0], isset($match[2]) ? '(' . trim($match[2][0]) . ')' : self::DEFAULT_PLACEHOLD_REGEX, $pattern);
            $parameters[$match[1][0]] = $match[1][0];
        }

        return [$pattern, $parameters];
    }

    public function getStaticRoutes($method)
    {
        return $this->statics[$method];
    }

    public function getDinamicRoutes($method, $offset)
    {
        if (!isset($this->dinamics[$method]) || !isset($this->dinamics[$method][$offset])) {
            return [];
        }

        $dinamics = $this->dinamics[$method][$offset];
        $chunks   = array_chunk($dinamics, round(1 + 2.33 * log(count($dinamics))), true); // Sturges' Formula

        return array_map(function ($routes) {
            $map = []; $regexes = []; $groupcount = 0;

            foreach ($routes as $regex => $route) {
                $paramscount      = count($route['params']);
                $groupcount       = max($groupcount, $paramscount) + 1;
                $regexes[]        = $regex . str_repeat('()', $groupcount - $paramscount - 1);
                $map[$groupcount] = [$route['action'], $route['params']];
            }

            return ['regex' => '~^(?|' . implode('|', $regexes) . ')$~', 'map' => $map];
        }, $chunks);
    }

}

/**
 * Give the mapper methods that abstract the first parameter relative to
 * HTTP methods into new mapper methods.
 *
 * @author Alex Rohleder <contato@alexrohleder.com.br>
 * @since 1.0.0
 */

trait HttpMethodMapper
{

    /**
     * Register a route into GET method.
     *
     * @param string                $pattern  The URi pattern that should be matched.
     * @param string|array|\closure $action   The action that must be executed in case of match.
     */
    public function get($pattern, $action)
    {
        return $this->set('get', $pattern, $action);
    }

    /**
     * Register a route into POST method.
     *
     * @param string                $pattern  The URi pattern that should be matched.
     * @param string|array|\closure $action   The action that must be executed in case of match.
     */
    public function post($pattern, $action)
    {
        $this->set('post', $pattern, $action);
    }

    /**
     * Register a route into PUT method.
     *
     * @param string                $pattern  The URi pattern that should be matched.
     * @param string|array|\closure $action   The action that must be executed in case of match.
     */
    public function put($pattern, $action)
    {
        $this->set('put', $pattern, $action);
    }

    /**
     * Register a route into PATCH method.
     *
     * @param string                $pattern  The URi pattern that should be matched.
     * @param string|array|\closure $action   The action that must be executed in case of match.
     */
    public function patch($pattern, $action)
    {
        $this->set('patch', $pattern, $action);
    }

    /**
     * Register a route into DELETE method.
     *
     * @param string                $pattern  The URi pattern that should be matched.
     * @param string|array|\closure $action   The action that must be executed in case of match.
     */
    public function delete($pattern, $action)
    {
        $this->set('delete', $pattern, $action);
    }

    /**
     * Register a route into all HTTP methods.
     *
     * @param string                $pattern  The URi pattern that should be matched.
     * @param string|array|\closure $action   The action that must be executed in case of match.
     */
    public function any($pattern, $action)
    {
        $this->match(self::$supported_http_methods, $pattern, $action);
    }

    /**
     * Register a route into all HTTP methods except by $method.
     *
     * @param string                $method   The method that must be excluded.
     * @param string                $pattern  The URi pattern that should be matched.
     * @param string|array|\closure $action   The action that must be executed in case of match.
     */
    public function except($method, $pattern, $action)
    {
        $this->match(array_diff(self::$supported_http_methods, (array) $method), $pattern, $action);
    }

    /**
     * Register a route into given HTTP method(s).
     *
     * @param string|array          $methods  The method that must be matched.
     * @param string                $pattern  The URi pattern that should be matched.
     * @param string|array|\closure $action   The action that must be executed in case of match.
     */
    public function match($methods, $pattern, $action)
    {
        foreach ((array) $methods as $method) {
            $this->set($method, $pattern, $action);
        }
    }

}

/**
 * Make mapper aware of the controllers, give to it a controller method that will
 * map all the controllers public methods that begins with an HTTP method.
 *
 * @author Alex Rohleder <contato@alexrohleder.com.br>
 * @since 1.0.0
 */

trait ControllerMapper
{

    /**
     * Maps all the controller methods that begins with a HTTP method, and maps the rest of
     * name as a uri. The uri will be the method name with slashes before every camelcased 
     * word and without the HTTP method prefix. 
     * e.g. getSomePage will generate a route to: GET some/page
     *
     * @param string|object $controller The controller name or representation.
     * @param bool          $prefix     Dict if the controller name should prefix the path.
     */
    public function controller($controller, $prefix = true)
    {
        if (!$methods = get_class_methods($controller)) {
            throw new Exception('The controller class coul\'d not be inspected.');
        }

        $methods = $this->getControllerMethods($methods);
        $prefix = $this->getPathPrefix($prefix, $controller);

        foreach ($methods as $httpmethod => $classmethods) {
            foreach ($classmethods as $classmethod) {
                $uri = preg_replace_callback('~(^|[a-z])([A-Z])~', [$this, 'getControllerAction'], $classmethod);

                $method  = $httpmethod . $classmethod;
                $dinamic = $this->getMethodDinamicPattern($controller, $method);

                $this->match($httpmethod, $prefix . "$uri$dinamic", "$controller#$method");
            }
        }
    }

    /**
     * Give a prefix for the controller routes paths.
     *
     * @param bool $prefix Must prefix?
     * @param string|object $controller The controller name or representation.
     *
     * @return string
     */
    protected function getPathPrefix($prefix, $controller)
    {
        $path = '/';

        if ($prefix === true) {
            $path .= $this->getControllerName($controller);
        }

        return $path;
    }

    /**
     * Transform camelcased strings into URIs.
     *
     * @return string
     */
    public function getControllerAction($matches)
    {
        return strtolower(strlen($matches[1]) ? $matches[1] . '/' . $matches[2] : $matches[2]);
    }

    /**
     * Get the controller name without the suffix Controller.
     *
     * @return string
     */
    public function getControllerName($controller)
    {
        if (is_object($controller)) {
            $controller = get_class($controller);
        }

        return strtolower(strstr($controller, 'Controller', true));
    }

    /**
     * Maps the controller methods to HTTP methods.
     *
     * @param array $methods All the controller public methods
     * @return array An array keyed by HTTP methods and their controller methods.
     */
    protected function getControllerMethods($methods)
    {
        $mapmethods = [];
        $httpmethods = self::$supported_http_methods;

        foreach ($methods as $classmethod) {
            foreach ($httpmethods as $httpmethod) {
                if (strpos($classmethod, $httpmethod) === 0) {
                    $mapmethods[$httpmethod][] = substr($classmethod, strlen($httpmethod));
                }
            }
        }

        return $mapmethods;
    }

    /**
     * Inspect a method seeking for parameters and make a dinamic pattern.
     *
     * @param string|object $controller The controller representation.
     * @param string        $method     The method to be inspected name.
     *
     * @return string The resulting URi.
     */
    protected function getMethodDinamicPattern($controller, $method)
    {
        $method = new ReflectionMethod($controller, $method);
        $uri    = '';

        if ($parameters = $method->getParameters())
        {
            $count = count($parameters);
            $types = $this->getParamsConstraint($method);

            for ($i = 0; $i < $count; ++$i) {
                $parameter = $parameters[$i];

                if ($parameter->isOptional()) {
                    $uri .= '[';
                }

                $uri .= $this->getUriConstraint($parameter, $types);
            }

            for ($i = $i - 1; $i >= 0; --$i) {
                if ($parameters[$i]->isOptional()) {
                    $uri .= ']';
                }
            }
        }

        return $uri;
    }

    /**
     * Return a URi segment based on parameters constraints.
     *
     * @param ReflectionParameter $parameter The parameter base to build the constraint.
     * @param array $types All the parsed constraints.
     *
     * @return string
     */
    protected function getUriConstraint(ReflectionParameter $parameter, $types)
    {
        $name = $parameter->name;
        $uri  = '/{' . $name;

        if (isset($types[$name])) {
            return  $uri . ':' . $types[$name] . '}';
        } else {
            return $uri . '}';
        }
    }

    /**
     * Get all parameters with they constraint.
     *
     * @param ReflectionMethod $method The method to be inspected name.
     * @return array All the parameters with they constraint.
     */
    protected function getParamsConstraint(ReflectionMethod $method)
    {
        $params = [];
        preg_match_all('~\@param\s(' . implode('|', array_keys($this->types)) . ')\s\$([a-zA-Z]+)\s(Match \((.+)\))?~', 
            $method->getDocComment(), $types, PREG_SET_ORDER);

        foreach ((array) $types as $type) {
            $params[$type[2]] = $this->getParamConstraint($type);
        }

        return $params;
    }

    /**
     * Convert PHPDoc type to a constraint.
     *
     * @param string $type The PHPDoc type.
     * @return string The Constraint string.
     */
    protected function getParamConstraint($type)
    {
        if (isset($type[4])) {
            return $type[4];
        }

        return self::$pattern_wildcards[$type[1]];
    }

}

trait ResourceMapper
{
    
    /**
     * A map of all routes of resources.
     *
     * @var array
     */
    protected $map = [
        'index' => ['get', '/:name'],
        'make' => ['get', '/:name/make'],
        'create' => ['post', '/:name'],
        'show' => ['get', '/:name/{id}'],
        'edit' => ['get', '/:name/{id}/edit'],
        'update' => ['put', '/:name/{id}'],
        'delete' => ['delete', '/:name/{id}']
    ];

    /**
     * Resource routing allows you to quickly declare all of the common routes for a given resourceful controller. 
     * Instead of declaring separate routes for your index, show, new, edit, create, update and destroy actions, 
     * a resourceful route declares them in a single line of code
     *
     * @param string|object $controller The controller name or representation.
     * @param array         $options Some options like, 'as' to name the route pattern, 'only' to
     *                               explicty say that only this routes will be registered, and 
     *                               except that register all the routes except the indicates.
     */
    public function resource($controller, array $options = array())
    {
        $name = $this->getName($controller, $options);
        $actions = $this->getActions($options);

        foreach ($actions as $action => $map) {
            $this->match($map[0], str_replace(':name', $name, $map[1]), 
                is_string($controller) ? "$controller#$action" : [$controller, $action]);
        }
    }

    /**
     * Get the name of controller or an defined name, that will be used to make the URis.
     *
     * @return string
     */
    protected function getName($controller, array $options)
    {
        if (isset($options['as'])) {
            return $options['as'];
        }

        if (is_object($controller)) {
            $controller = get_class($controller);
        }

        return strtolower(strstr(array_reverse(explode('\\', $controller))[0], 'Controller', true));
    }

    /**
     * Parse the options to find out what actions will be registered.
     *
     * @return string
     */
    protected function getActions($options)
    {
        $actions = $this->map;

        if (isset($options['only'])) {
            $actions = $this->getFilteredActions($options['only'], true);
        }

        if (isset($options['except'])) {
            $actions = $this->getFilteredActions($options['except'], false);
        }

        return $actions;
    }

    /**
     * Return an array with the methods and urls for the given resource.
     *
     * @param string $methods The given resource actions.
     * @param bool   $exists  Dict if the informed $methods must be included or excluded.
     *
     * @return array
     */
    protected function getFilteredActions($methods, $exists)
    {
        $actions = $this->map;
        $methods = array_change_key_case(array_flip($methods), CASE_LOWER);

        foreach ($actions as $action => $map) {
            if ((isset($methods[$map[0]]) && !$exists)
                    || (!isset($methods[$map[0]]) && $exists)) {
                unset($actions[$action]);
            }   
        }

        return $actions;
    }

}
