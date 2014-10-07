<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class SearchController extends Controller
{
    public function searchAction(Request $request)
    {
        $term = $request->request->get('term', null);
        return new RedirectResponse($this->get("router")->generate("tbn_user_search", ["term" => $term]));
    }
}