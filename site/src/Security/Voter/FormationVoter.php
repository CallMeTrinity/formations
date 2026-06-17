<?php

namespace App\Security\Voter;

use App\Entity\Formation;
use App\Enum\Visibility;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class FormationVoter extends Voter
{
    public const VIEW = 'VIEW';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof Formation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        \assert($subject instanceof Formation);

        return match ($subject->getVisibility()) {
            Visibility::PUBLIC => true,
            Visibility::BETA => $this->security->isGranted('ROLE_USER'),
            Visibility::DRAFT => $this->security->isGranted('ROLE_ADMIN'),
        };
    }
}
