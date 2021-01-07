<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

abstract class OAuthCrudController extends AbstractCrudController
{
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['facebook_id', 'facebook_access_token', 'facebook_refresh_token', 'facebook_email', 'facebook_expires', 'facebook_realname', 'facebook_profile_picture', 'google_id', 'google_access_token', 'google_refresh_token', 'google_email', 'google_expires', 'google_realname', 'google_profile_picture', 'twitter_id', 'twitter_access_token', 'twitter_refresh_token', 'twitter_email', 'twitter_expires', 'twitter_nickname', 'twitter_realname', 'twitter_profile_picture', 'id']);
    }

    public function configureFields(string $pageName): iterable
    {
        $facebookId = TextField::new('facebook_id');
        $facebookAccessToken = TextField::new('facebook_access_token');
        $facebookRefreshToken = TextField::new('facebook_refresh_token');
        $facebookEmail = TextField::new('facebook_email');
        $facebookExpires = IntegerField::new('facebook_expires');
        $facebookRealname = TextField::new('facebook_realname');
        $facebookProfilePicture = TextField::new('facebook_profile_picture');
        $googleId = TextField::new('google_id');
        $googleAccessToken = TextField::new('google_access_token');
        $googleRefreshToken = TextField::new('google_refresh_token');
        $googleEmail = TextField::new('google_email');
        $googleExpires = IntegerField::new('google_expires');
        $googleRealname = TextField::new('google_realname');
        $googleProfilePicture = TextField::new('google_profile_picture');
        $twitterId = TextField::new('twitter_id');
        $twitterAccessToken = TextField::new('twitter_access_token');
        $twitterRefreshToken = TextField::new('twitter_refresh_token');
        $twitterEmail = TextField::new('twitter_email');
        $twitterExpires = IntegerField::new('twitter_expires');
        $twitterNickname = TextField::new('twitter_nickname');
        $twitterRealname = TextField::new('twitter_realname');
        $twitterProfilePicture = TextField::new('twitter_profile_picture');
        $id = IdField::new('id', 'ID');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $facebookId, $googleId, $twitterId];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $facebookId, $facebookAccessToken, $facebookRefreshToken, $facebookEmail, $facebookExpires, $facebookRealname, $facebookProfilePicture, $googleId, $googleAccessToken, $googleRefreshToken, $googleEmail, $googleExpires, $googleRealname, $googleProfilePicture, $twitterId, $twitterAccessToken, $twitterRefreshToken, $twitterEmail, $twitterExpires, $twitterNickname, $twitterRealname, $twitterProfilePicture];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$facebookId, $facebookAccessToken, $facebookRefreshToken, $facebookEmail, $facebookExpires, $facebookRealname, $facebookProfilePicture, $googleId, $googleAccessToken, $googleRefreshToken, $googleEmail, $googleExpires, $googleRealname, $googleProfilePicture, $twitterId, $twitterAccessToken, $twitterRefreshToken, $twitterEmail, $twitterExpires, $twitterNickname, $twitterRealname, $twitterProfilePicture];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$facebookId, $facebookAccessToken, $facebookRefreshToken, $facebookEmail, $facebookExpires, $facebookRealname, $facebookProfilePicture, $googleId, $googleAccessToken, $googleRefreshToken, $googleEmail, $googleExpires, $googleRealname, $googleProfilePicture, $twitterId, $twitterAccessToken, $twitterRefreshToken, $twitterEmail, $twitterExpires, $twitterNickname, $twitterRealname, $twitterProfilePicture];
        }
    }
}
