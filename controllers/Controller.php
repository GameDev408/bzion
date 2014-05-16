<?php

use Symfony\Component\HttpFoundation\Response;

/**
 * The Controller class represents a bunch of pages relating to the same
 * subject (Model in most cases) - for example, there is a PlayerController,
 * a TeamController and a HomeController.
 *
 * Controllers contain special methods called 'actions', which are essentially
 * different pages performing different actions - for example, the
 * TeamController might contain a 'show' action, which renders the team's page,
 * and a 'new' action, which renders the page that is shown to the user when
 * they want to create a new team.
 *
 * Actions have some unique characteristics. Take a look at this sample action:
 *
 * <pre><code>public function showAction(Request $request, Team $team) {
 *   return array('team' => $team);
 * }
 * </code></pre>
 *
 * The following route will make sure that `showAction()` handles the request:
 *
 * <pre><code>team_show:
 *   pattern:    /teams/{team}
 *   defaults: { _controller: 'Team', _action: 'show' }
 * </code></pre>
 *
 * First of all, the method's name should end with `Action`. The parameters
 * are passed dynamically, and the order is insignificant.
 *
 * You can request Symfony's Request or Session class, or even a model, which
 * will be generated based on the route parameters. For example, the route
 * pattern `/posts/{post}/comments/{commentId}` (note how you can use both
 * `comment` and `commentId` as parameters - just make sure to use the correct
 * variable name on the method later) and can be used with actions like these:
 *
 * <code>
 * public function sampleAction
 *   (Request $request, NewsArticle $post, Comment $comment)
 * </code>
 *
 * <code>
 * public function sampleAction
 *   (NewsArticle $post, Session $session, Request $request, Comment $comment)
 * </code>
 *
 * A method's return value can be:
 * - Symfony's Response Class
 * - A string representing the text you want the user to see
 * - An array representing the variables you want to pass to the controller's
 *   view, so that it can be rendered
 */
abstract class Controller
{
    /**
     * Parameters specified by the route
     * @var array
     */
    protected $parameters;

    /**
     *
     * @param array $parameters The array returned by $router->matchRequest()
     */
    public function __construct($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Returns the controller that is assigned to a route
     *
     * @param  array      $parameters The array returned by $router->matchRequest()
     * @return Controller The controller
     */
    public static function getController($parameters)
    {
        $parameters = $parameters->all();
        $ref = new ReflectionClass($parameters['_controller'] . 'Controller');

        return $ref->newInstance($parameters);
    }

    /**
     * Call the controller's action specified by the $parameters array
     *
     * @param  string|null $action The action name to call, null to invoke the default one
     * @return Response    The action's response
     */
    public function callAction($action=null)
    {
        if (!$action)
            $action = $this->parameters['_action'];

        $this->setup();
        $response = $this->forward($action);
        $this->cleanup();

        return $response;
    }

    /**
     * Forward the request to another action
     *
     * Please note that this doesn't generate an HTTP redirect, but an
     * internal one - the user sees the original URL, but a different page
     *
     * @todo Forward the request to another controller
     * @param  string   $action The action to forward the request to
     * @param  array    $params An additional associative array of parameters to provide to the action
     * @return Response
     */
    protected function forward($action, $params=array())
    {
        $args = array_merge($this->parameters, $params);

        $ret = $this->callMethod($action . 'Action', $args);

        return $this->handleReturnValue($ret, $action);
    }

    /**
     * Method that will be called before any action
     *
     * @return void
     */
    public function setup()
    {
    }

    /**
     * Method that will be called after all actions
     *
     * @return void
     */
    public function cleanup()
    {
    }

    /**
     * Call one of the controller's methods
     *
     * The arguments are passed dynamically to the method, based on its
     * definition - check the description of the Controller class for more
     * information
     *
     * @param  string $method     The name of the method
     * @param  array  $parameters An associative array representing the method's parameters
     * @return mixed  The return value of the called method
    */
    protected function callMethod($method, $parameters)
    {
        $ref = new ReflectionMethod($this, $method);
        $params = array();

        foreach ($ref->getParameters() as $p) {
            if ($model = $this->getModelFromParameters($p, $parameters)) {
                // The parameter's class is a Model
                // Get its slug from the request and send a Model to the method
                $params[] = $model;
            } elseif (isset($parameters[$p->name])) {
                $params[] = $parameters[$p->name];
            } elseif ($p->isOptional()) {
                $params[] = $p->getDefaultValue();
            } else {
                throw new MissingArgumentException("Missing parameter $p->name");
            }
        }

        return $ref->invokeArgs($this, $params);
    }

    /**
     * Find what to pass as an argument on an action
     *
     * @param ReflectionParameter $modelParameter  The model's parameter we want to investigate
     * @param array               $routeParameters The route's parameters
     */
    protected function getModelFromParameters($modelParameter, $routeParameters)
    {
        $refClass = $modelParameter->getClass();
        $paramName  = $modelParameter->getName();

        if ($refClass === null)
            return null;

        if ($refClass->getName() == "Symfony\Component\HttpFoundation\Request")
            return $this->getRequest();

        if ($refClass->getName() == "Symfony\Component\HttpFoundation\Session\Session")
            return $this->getRequest()->getSession();

        if (!$refClass->isSubclassOf("Model"))
            return null;

        // Look for the object's ID/slugs in the routeParameters array
        if (isset($routeParameters[$paramName])) {
            if (is_object($routeParameters[$paramName]) &&
                $refClass->getName() === get_class($routeParameters[$paramName])
            ) return $routeParameters[$paramName]; // The model has already been instantiated - we don't need to do anything

            return $refClass->getMethod("fetchFromSlug")->invoke(null, $routeParameters[$paramName]);
        }

        if (isset($routeParameters[$paramName . 'Id']))
            return $refClass->newInstance($routeParameters[$paramName . 'Id']);
    }

    /**
     * Get a Response from the return value of an action
     * @param  mixed    $return Whatever the method returned
     * @param  string   $action The name of the action
     * @return Response The response that the controller wants us to send to the client
     */
    private function handleReturnValue($return, $action)
    {
        if ($return instanceof Response)
            return $return;

        $content = null;
        if (is_array($return)) {
            // The controller is probably expecting us to show a view to the
            // user, using the array provided to set variables for the template
            $templatePath = $this->getName() . "/$action.html.twig";
            $content = $this->render($templatePath, $return);
        } elseif (is_string($return)) {
            $content = $return;
        }

        return new Response($content);
    }

    /**
     * Returns the name of the controller without the "Controller" part
     * @return string
     */
    protected function getName()
    {
        return preg_replace('/Controller$/', '', get_called_class());
    }

    /**
     * Generates a URL from the given parameters.
     * @param  string  $name       The name of the route
     * @param  mixed   $parameters An array of parameters
     * @param  boolean $absolute   Whether to generate an absolute URL
     * @return string  The generated URL
     */
     public static function generate($name, $parameters = array(), $absolute = false)
     {
        return Service::getGenerator()->generate($name, $parameters, $absolute);
     }

     /**
      * Gets the browser's request
      * @return Symfony\Component\HttpFoundation\Request
      */
    public static function getRequest()
    {
        return Service::getRequest();
    }

    /**
     * Renders a view
     * @param  string $view       The view name
     * @param  array  $parameters An array of parameters to pass to the view
     * @return string The rendered view
     */
    protected function render($view, $parameters=array())
    {
        $template = Service::getTemplateEngine();

        return $template->render($view, $parameters);
    }
}

class MissingArgumentException extends Exception
{
}
