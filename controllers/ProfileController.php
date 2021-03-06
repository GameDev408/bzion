<?php

use BZIon\Form\Creator\ProfileFormCreator;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class ProfileController extends HTMLController
{
    public function setup()
    {
        $this->requireLogin();
    }

    public function editAction(Player $me, Request $request)
    {
        $creator = new ProfileFormCreator($me);
        $form    = $creator->create()->handleRequest($request);

        if ($form->isValid()) {
            $me->setDescription($form->get('description')->getData());
            $me->setTimezone($form->get('timezone')->getData());
            $me->setCountry($form->get('country')->getData());
            $me->setReceives($form->get('receive')->getData());

            if ($form->get('delete_avatar')->isClicked()) {
                $me->resetAvatar();
            } else {
                $me->setAvatarFile($form->get('avatar')->getData());
            }

            $email = $form->get('email')->getData();
            if ($email !== $me->getEmailAddress()) {
                // User has changed their address, send a confirmation mail
                $me->setEmailAddress($email);
                $this->sendConfirmationMessage($me);
            }
        }

        return array("player" => $me, "form" => $form->createView());
    }

    /**
     * @todo Expire verification codes
     */
    public function confirmAction(Player $me, $token)
    {
        if (!$me->getEmailAddress()) {
            throw new ForbiddenException("You need to have an e-mail address to confirm!");
        }

        if ($me->isVerified()) {
            throw new ForbiddenException("You have already been verified");
        }

        if (!$me->isCorrectConfirmCode($token)) {
            throw new ForbiddenException("Invalid verification code");
        }

        $me->setVerified(true);

        $this->getFlashBag()->add('success', "Your e-mail address has been successfully verified");

        return new RedirectResponse($me->getUrl());
    }

    public function showAction(Player $me)
    {
        return new RedirectResponse($me->getUrl());
    }

    /**
     * Send a confirmation e-mail to a player
     * @param Player $player The receiving player
     */
    private function sendConfirmationMessage($player)
    {
        if ($player->getConfirmCode() === null) {
            // The player has no confirmation code, don't send them a message
            return;
        }

        $from = $this->container->getParameter('bzion.email.from');
        $title = $this->container->getParameter('bzion.site.name');

        if (!$from) {
            $this->getLogger()->addError('Unable to send verification e-mail message to player due to the "From:" address not being specified', array(
                'player' => array('id' => $player->getId(), 'username' => $player->getUsername())
            ));
            return;
        }

        $message = Swift_Message::newInstance()
            ->setSubject($title . ' Email Confirmation')
            ->setFrom(array($from => $title))
            ->setTo($player->getEmailAddress())
            ->setBody($this->render('Email/confirm.txt.twig',  array('player' => $player)))
            ->addPart($this->render('Email/confirm.html.twig', array('player' => $player)), 'text/html');

        $this->container->get('mailer')->send($message);

        $this->getFlashBag()->add('info',
            'Please check your inbox in order to confirm your email address.');
    }
}
