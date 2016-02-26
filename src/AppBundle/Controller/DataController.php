<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class DataController extends Controller
{
    /**
     * @Route("/data/library", name="file_library")
     * @param Request $request
     */
    public function fileLibraryAction(Request $request)
    {

    }

    /**
     * @param Request $request
     */
    public function uploadFileAction(Request $request)
    {

    }
}