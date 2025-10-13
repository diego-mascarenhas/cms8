<?php

namespace App\Http\Controllers;

use App\Models\TaskBoard;
use Illuminate\Http\Request;

class TaskBoardController extends Controller
{
    public function index()
    {
        $boards = TaskBoard::orderBy('order')->get();

        return view('task_board.index', compact('boards'));
    }

    public function create()
    {
        return view('task_board.form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        $teamId = auth()->user()->currentTeam->id;
        $isDefault = $request->has('is_default') ? $request->is_default : false;

        TaskBoard::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $request->name,
                'description' => $request->description,
                'is_default' => $isDefault,
                'team_id' => $teamId,
                'order' => $request->id ? TaskBoard::find($request->id)->order : (TaskBoard::max('order') + 1),
            ],
        );

        return redirect()->route('task-board.index')->with('success', 'Board saved successfully.');
    }

    public function edit(string $id)
    {
        $data = TaskBoard::findOrFail($id);

        return view('task_board.form', compact('data'));
    }

    public function destroy(string $id)
    {
        $board = TaskBoard::findOrFail($id);

        // Don't allow deletion of default board if it's the only one
        if ($board->is_default && TaskBoard::count() <= 1)
        {
            return redirect()->route('task-board.index')->with('error', 'Cannot delete the only board.');
        }

        // Move tasks to default board if one exists, otherwise create a new default board
        if (TaskBoard::count() > 1)
        {
            $defaultBoard = TaskBoard::where('id', '!=', $id)->where('is_default', true)->first();

            if (! $defaultBoard)
            {
                $defaultBoard = TaskBoard::where('id', '!=', $id)->first();
                $defaultBoard->is_default = true;
                $defaultBoard->save();
            }

            // Move all tasks to the default board
            $board->tasks()->update(['board_id' => $defaultBoard->id]);
        }

        $board->delete();

        return redirect()->route('task-board.index')->with('success', 'Board deleted successfully.');
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'boards' => 'required|array',
            'boards.*.id' => 'required|exists:task_boards,id',
            'boards.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->boards as $boardData)
        {
            $board = TaskBoard::findOrFail($boardData['id']);
            $board->order = $boardData['order'];
            $board->save();
        }

        return response()->json(['success' => true]);
    }
}
