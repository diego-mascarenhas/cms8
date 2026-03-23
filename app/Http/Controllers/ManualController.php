<?php

namespace App\Http\Controllers;

class ManualController extends Controller
{
    /**
     * Display the user manual index
     */
    public function index()
    {
        return view('manual.index');
    }

    /**
     * Display the getting started / overview section
     */
    public function gettingStarted()
    {
        return view('manual.getting-started');
    }

    /**
     * Display the dashboard and today section
     */
    public function dashboard()
    {
        return view('manual.dashboard');
    }

    /**
     * Display the contacts and prospecting section
     */
    public function contacts()
    {
        return view('manual.contacts');
    }

    /**
     * Display the clients section
     */
    public function clients()
    {
        return view('manual.clients');
    }

    /**
     * Display the collaborators section
     */
    public function collaborators()
    {
        return view('manual.collaborators');
    }

    /**
     * Display the services section
     */
    public function services()
    {
        return view('manual.services');
    }

    /**
     * Display the projects section
     */
    public function projects()
    {
        return view('manual.projects');
    }

    /**
     * Display the tasks and time tracking section
     */
    public function tasks()
    {
        return view('manual.tasks');
    }

    /**
     * Display the chat and WhatsApp section
     */
    public function chat()
    {
        return view('manual.chat');
    }

    /**
     * Display the products and orders (e-commerce) section
     */
    public function productsAndOrders()
    {
        return view('manual.products-and-orders');
    }

    /**
     * Display the billing section (invoices, payments, income, expenses)
     */
    public function billing()
    {
        return view('manual.billing');
    }

    /**
     * Display the messages and templates (campaigns) section
     */
    public function campaigns()
    {
        return view('manual.campaigns');
    }

    /**
     * Display the team section (users, departments)
     */
    public function team()
    {
        return view('manual.team');
    }

    /**
     * Display the rest of features (enterprises, contents, prompts, etc.)
     */
    public function moreFeatures()
    {
        return view('manual.more-features');
    }
}
