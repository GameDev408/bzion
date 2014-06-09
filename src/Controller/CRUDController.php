<?php

use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * A controller with actions for creating, reading, updating and deleting models
 * @package BZiON\Controllers
 */
abstract class CRUDController extends JSONController
{
    /**
     * Create a form for a model
     * @return Form
     */
    protected function createForm()
    {
        return Service::getFormFactory()->createBuilder()
            ->add('create', 'submit')
            ->getForm();
    }

    /**
     * Enter the data of a valid form into the database
     * @param  Form   $form    The submitted form
     * @param  Player $creator The player who enters the data
     * @return Model
     */
    protected function enter($form, $creator)
    {
    }

    /**
     * Make sure that the data of a form is valid
     * @param  Form $form The submitted form
     * @return void
     */
    protected function validate($form)
    {
    }

    /**
     * Fill a form with the model's data
     * @param Form  $form  The form to fill
     * @param Model $model The model to use to fill the form
     */
    protected function fill($form, $model)
    {
    }

    /**
     * Update a model in the database
     * @param  Form   $form  The form that will be used to update the model
     * @param  Model  $model The model that will be updated
     * @param  Player $me    The player that wants to edit the model
     * @return Model  The updated model
     */
    protected function update($form, $article, $me)
    {
    }

    /**
     * Delete a model
     * @throws ForbiddenException
     * @param  PermissionModel    $model The model we want to delete
     * @param  Player             $me    The user who wants to delete the model
     * @return mixed              The response to show to the user
     */
    protected function delete(PermissionModel $model, Player $me)
    {
        if (!$this->canDelete($me, $model))
            throw new ForbiddenException($this->getMessage($model, 'softDelete', 'forbidden'));

        $session        = $this->getRequest()->getSession();
        $successMessage = $this->getMessage($model, 'softDelete', 'success');
        $redirection    = $this->redirectToList($model);

        return $this->showConfirmationForm(function () use (&$model, &$session, $successMessage, $redirection) {
            $model->delete();
            $session->getFlashBag()->add('success', $successMessage);

            return $redirection;
        }, $this->getMessage($model, 'softDelete', 'confirm'), "Delete");
    }

    /**
     * Create a model
     *
     * This method requires that you have implemented createForm() and enter()
     * @throws ForbiddenException
     * @param  Player             $me The user who wants to create the model
     * @return mixed              The response to show to the user
     */
    protected function create(Player $me)
    {
        if (!$this->canCreate($me))
            throw new ForbiddenException($this->getMessage($this->getName(), 'create', 'forbidden'));

        $form = $this->createForm();
        $form->handleRequest($this->getRequest());

        if ($form->isSubmitted()) {
            $this->validate($form);
            if ($form->isValid()) {
                $model = $this->enter($form, $me);
                $this->getRequest()->getSession()->getFlashBag()->add("success",
                    $this->getMessage($model, 'create', 'success'));

                return $this->redirectTo($model);
            }
        }

        return array("form" => $form->createView());
    }

    /**
     * Edit a model
     *
     * This method requires that you have implemented createForm() and update()
     * @throws ForbiddenException
     * @param  PermissionModel    $model The model we want to edit
     * @param  Player             $me    The user who wants to edit the model
     * @param  string             $type  The name of the variable to pass to the view
     * @return mixed              The response to show to the user
     */
    protected function edit(PermissionModel $model, Player $me, $type)
    {
        if (!$this->canEdit($me, $model))
            throw new ForbiddenException($this->getMessage($model, 'edit', 'forbidden'));

        $form = $this->createForm();
        $this->fill($form, $model);
        $form->handleRequest($this->getRequest());

        if ($form->isSubmitted()) {
            $this->validate($form);
            if ($form->isValid()) {
                $model = $this->update($form, $model, $me);
                $this->getRequest()->getSession()->getFlashBag()->add("success",
                    $this->getMessage($model, 'edit', 'success'));

                return $this->redirectTo($model);
            }
        }

        return array("form" => $form->createView(), $type => $model);
    }

    /**
     * Find whether a player can delete a model
     *
     * @param  Player          $player The player who wants to delete the model
     * @param  PermissionModel $model  The model that will be deleted
     * @return boolean
     */
    protected function canDelete($player, $model)
    {
        return $player->hasPermission($model->getSoftDeletePermission());
    }

    /**
     * Find whether a player can create a model
     *
     * @param  Player  $player The player who wants to create a model
     * @return boolean
     */
    protected function canCreate($player)
    {
        $modelName = $this->getName();

        return $player->hasPermission($modelName::getCreatePermission());
    }

    /**
     * Find whether a player can edit a model
     *
     * @param  Player          $player The player who wants to delete the model
     * @param  PermissionModel $model  The model which will be edited
     * @return boolean
     */
    protected function canEdit($player, $model)
    {
        return $player->hasPermission($model->getEditPermission());
    }

    /**
     * Get a redirection response to a model
     *
     * Goes to a list of models of the same type if the provided model does not
     * have a URL
     *
     * @param  Model    $model The model to redirect to
     * @return Response
     */
    private function redirectTo($model)
    {
        if ($model instanceof UrlModel) {
            return new RedirectResponse($model->getUrl());
        } else {
            return $this->redirectToList($model);
        }
    }

    /**
     * Get a redirection response to a list of models
     *
     * @param  Model    $model The model to whose list we should redirect
     * @return Response
     */
    private function redirectToList($model)
    {
        $route = strtolower($model->getTypeForHumans()) . '_list';
        $url = Service::getGenerator()->generate($route);

        return new RedirectResponse($url);
    }

    /**
     * Get a message to show to the user
     * @param  Model|string $model  The model (or type) to show a message for
     * @param  string       $action The action that will be performed (softDelete, hardDelete, create or edit)
     * @param  string       $status The message's status (confirm, error or success)
     * @return string
     */
    private function getMessage($model, $action, $status, $escape=true)
    {
        if ($model instanceof Model) {
            $type = strtolower($model->getTypeForHumans());

            if ($model instanceof NamedModel) {
                // Twig will not escape the message on confirmation forms
                $name = $model->getName();
                if ($status == 'confirm')
                    $name = Model::escape($name);

                $messages = $this->getMessages($type, $name);

                return $messages[$action][$status]['named'];
            } else {
                $messages = $this->getMessages($type);

                return $messages[$action][$status]['unnamed'];
            }
        } else {
            $messages = $this->getMessages(strtolower($model));

            return $messages[$action][$status];
        }
    }

    /**
     * Get a list of messages to show to the user
     * @param  string $type The type of the model that the message refers to
     * @param  string $name The name of the model
     * @return array
     */
    private function getMessages($type, $name='')
    {
        return array(
            'softDelete' => array(
                'confirm' => array(
                    'named'   => "Are you sure you want to delete <strong>$name</strong>?",
                    'unnamed' => "Are you sure you want to delete this $type?",
                ),
                'forbidden' => array(
                    'named'   => "You cannot delete the $type $name",
                    'unnamed' => "You can't delete this $type",
                ),
                'success' => array(
                    'named'   => "The $type $name was deleted successfully",
                    'unnamed' => "The $type was deleted successfully",
                ),
            ),
            'edit' => array(
                'forbidden' => array(
                    'named'   => "You are not allowed to edit the $type $name",
                    'unnamed' => "You aren't allowed to edit this $type",
                ),
                'success' => array(
                    'named'   => "The $type $name has been successfully updated",
                    'unnamed' => "The $type was updated successfully",
                ),
            ),
            'create' => array(
                'forbidden' => "You are not allowed to create a new $type",
                'success' => array(
                    'named'   => "The $type $name was created successfully",
                    'unnamed' => "The $type was created successfully",
                ),
            ),
        );
    }
}
