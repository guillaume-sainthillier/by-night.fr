<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\AdminZone1Repository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminZone1Repository::class)]
class AdminZone1 extends AdminZone
{
}
