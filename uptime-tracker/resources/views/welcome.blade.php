<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @fonts

       @vite(['resources/css/app.css', 'resources/js/app.js'])
       
    </head>
    <body class="bg-dark text-light d-flex p-4 p-lg-5 justify-content-lg-center min-vh-100 flex-column">
        <div id="main" class="container w-100" style="max-width: 1000px;">
            <div class="row g-4">
                <div class="col-md-5 d-flex flex-column gap-4">

                    <div id="adder" class="mcard p-4 h-100">
                        <h1>Add Url</h1>
                        <div class="d-flex gap-2 mt-3">
                            <input type="text" id="new-url-input" class="form-control bg-dark text-light border-secondary" placeholder="https://example.com">
                            <button id="add-url-btn" class="btn btn-secondary px-4">+</button>
                        </div>
                    </div>
                    <div id="status" class="mcard flex-grow-1">
                        <h1>status</h1>
                        <div id="log-container" class="mt-2 flex-grow-1 overflow-auto bg-dark p-2 border border-secondary rounded font-monospace" style="font-size: 0.85rem;">

                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div id="control" class="mcard p-4 h-100">
                        <h4>Controller</h4>
                        <label for="">Delay Per Url (minutes)</label>
                        <div class="d-flex gap-2 mb-4">
                            <input type="number" id="timer-input" class="form-control bg-dark text-light border-secondary w-25" value="1">
                            <button id="timer-submit-btn" class="btn btn-secondary px-4">Submit</button>
                            <button id="start-btn" class="btn btn-secondary px-4">Start</button>
                            <button id="stop-btn" class="btn btn-secondary px-4">Stop</button>
                        </div>
                        <label for=""> URLs</label>
                        <div id="urls" class="mcard">
                            <ul id="url-list" class="list-group list-group-flush border border-secondary rounded bg-dark overflow-auto" style="max-height: 250px;">
                                
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
