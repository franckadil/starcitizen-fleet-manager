<?php

namespace App\Listener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Security;

class TrackConnectionListener
{
    private $entityManager;
    private $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function onController(ControllerEvent $event): void
    {
        $token = $this->security->getToken();
        if ($token === null || $token instanceof SwitchUserToken) {
            // we don't want tracking when impersonation
            return;
        }

        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $user->setLastConnectedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
