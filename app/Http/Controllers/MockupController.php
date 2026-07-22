<?php

namespace App\Http\Controllers;

use App\Support\ManualDocumentation;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MockupController extends Controller
{
    public function index(): View
    {
        return view('mockups.index', [
            'mockups' => ManualDocumentation::mockups(),
        ]);
    }

    public function show(string $slug): View
    {
        $mockup = ManualDocumentation::mockup($slug);

        if ($mockup === null)
        {
            throw new NotFoundHttpException;
        }

        $view = 'mockups.'.$slug;

        if (! view()->exists($view))
        {
            throw new NotFoundHttpException;
        }

        return view($view, [
            'mockup' => $mockup,
            'mockups' => ManualDocumentation::mockups(),
        ]);
    }

    public function overview(): View
    {
        return $this->show('overview');
    }

    public function rolesFlow(): View
    {
        return $this->show('roles-flow');
    }

    public function clientJourney(): View
    {
        return $this->show('client-journey');
    }

    public function clientTicket(): View
    {
        return $this->show('client-ticket');
    }

    public function clientHome(): View
    {
        return $this->show('client-home');
    }

    public function contactForm(): View
    {
        return $this->show('contact-form');
    }

    public function clientForm(): View
    {
        return $this->show('client-form');
    }

    public function projectForm(): View
    {
        return $this->show('project-form');
    }

    public function taskForm(): View
    {
        return $this->show('task-form');
    }

    public function serviceForm(): View
    {
        return $this->show('service-form');
    }

    public function invoiceFlow(): View
    {
        return $this->show('invoice-flow');
    }

    public function collaboratorDay(): View
    {
        return $this->show('collaborator-day');
    }

    public function adminSetup(): View
    {
        return $this->show('admin-setup');
    }
}
