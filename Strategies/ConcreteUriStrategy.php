<?php

/**
 * Codeburner Framework.
 *
 * @author Alex Rohleder <contato@alexrohleder.com.br>
 * @copyright 2015 Alex Rohleder
 * @license http://opensource.org/licenses/MIT
 */

namespace Codeburner\Router\Strategies;

/**
 * Codeburner Router Component.
 *
 * @author Alex Rohleder <contato@alexrohleder.com.br>
 * @see https://github.com/codeburnerframework/router
 */
class ConcreteUriStrategy implements \Codeburner\Router\DispatcherStrategyInterface
{

    /**
     * Dispache the matched route action.
     *
     * @param  string|array|closure $action The matched route action.
     * @param  array                $params The route parameters.
     *
     * @return mixed The response of request.
     */
    public function dispatch($action, array $params)
    {
        if (is_array($action)) {
            return call_user_func_array([new $action[0], $action[1]], $params);
        } else {
            return call_user_func_array($action, $params);
        }
    }

}
