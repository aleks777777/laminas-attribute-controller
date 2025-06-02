<?php

namespace LaminasAttributeController\Security;

final class NullCurrentUser implements GetCurrentUser
{
    public function getCurrentUser(): null
    {
        return null;
    }

}