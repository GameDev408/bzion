<?php

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class TeamController extends CRUDController
{
    public function showAction(Team $team)
    {
        return array("team" => $team);
    }

    public function listAction()
    {
        return array("teams" => Team::getTeams());
    }

    public function createAction(Player $me)
    {
        return $this->create($me);
    }

    public function deleteAction(Player $me, Team $team)
    {
        return $this->delete($team, $me);
    }

    public function inviteAction(Team $team, Player $player, Player $me, FlashBag $flash)
    {
        $this->assertCanEdit($me, $team, "You are not allowed to invite a player to that team!");

        if ($team->isMember($player->getId()))
            throw new ForbiddenException("The specified player is already a member of that team.");

        return $this->showConfirmationForm(function () use (&$team, &$player, &$me, &$flash) {
            Invitation::sendInvite($player->getId(), $me->getId(), $team->getId());

            $message = "Player {$player->getUsername()} has been invited to {$team->getName()}";
            $flash->add('success', $message);

            return new RedirectResponse($team->getUrl());
        }, "Are you sure you want to invite {$player->getEscapedUsername()} to {$team->getEscapedName()}?");
    }

    public function kickAction(Team $team, Player $player, Player $me, FlashBag $flash)
    {
        $this->assertCanEdit($me, $team, "You are not allowed to kick a player off that team!");

        if ($team->getLeader()->getId() == $player->getId())
            throw new ForbiddenException("You can't kick the leader off their team.");

        if (!$team->isMember($player->getId()))
            throw new ForbiddenException("The specified player is not a member of that team.");

        return $this->showConfirmationForm(function () use (&$team, &$player, &$flash) {
            $team->removeMember($player->getId());

            $message = "Player {$player->getUsername()} has been kicked from {$team->getName()}";
            $flash->add('success', $message);

            return new RedirectResponse($team->getUrl());
        }, "Are you sure you want to kick {$player->getEscapedUsername()} from {$team->getEscapedName()}?", "Kick");
    }

    public function abandonAction(Team $team, Player $me, FlashBag $flash)
    {
        if (!$team->isMember($me->getId()))
            throw new ForbiddenException("You are not a member of that team!");

        if ($team->getLeader()->getId() == $me->getId())
            throw new ForbiddenException("You can't abandon the team you are leading.");

        return $this->showConfirmationForm(function () use (&$team, &$me, &$flash) {
            $team->removeMember($me->getId());

            $message = "You have left {$team->getName()}";
            $flash->add('success', $message);

            return new RedirectResponse($team->getUrl());
        }, "Are you sure you want to abandon {$team->getEscapedName()}?", "Abandon");
    }

    public function createForm()
    {
        // TODO: Add validation constraint for teams with same name
        return Service::getFormFactory()->createBuilder()
            ->add('name', 'text', array(
                'constraints' => array(
                    new NotBlank(), new Length(array(
                        'min' => 2,
                        'max' => 32,
                    ))
                )
            ))
            ->add('description', 'textarea', array(
                'required' => false
            ))
            ->add('create', 'submit')
            ->getForm();
    }

    protected function enter($form, $creator)
    {
        return Team::createTeam(
            $form->get('name')->getData(),
            $creator->getId(),
            '',
            $form->get('description')->getData()
        );
    }

    protected function validate($form)
    {
        $name = $form->get('name');
        $team = Team::getFromName($name->getData());

        // The name for the team that the user gave us already exists
        // TODO: This takes deleted teams into account, do we want that?
        if ($team->isValid())
            $name->addError(new FormError("A team called {$team->getEscapedName()} already exists"));
    }

    protected function canCreate($player)
    {
        if ($player->getTeam()->isValid())
            throw new ForbiddenException("You need to abandon your current team before you can create a new one");

        return parent::canCreate($player);
    }

    /*
     * Make sure that a player can edit a team
     *
     * Throws an exception if a player is not an admin or the leader of a team
     * @throws HTTPException
     * @param  Player        $player  The player to test
     * @param  Team          $team    The team
     * @param  string        $message The error message to show
     * @return void
     */
    private function assertCanEdit(Player $player, Team $team, $message="You are not allowed to edit that team")
    {
        if (!$player->hasPermission(Permission::EDIT_TEAM))
            if ($team->getLeader()->getId() != $player->getId())
                throw new ForbiddenException($message);
    }
}
