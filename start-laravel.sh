#!/bin/bash

SESSION="laravel"

tmux kill-session -t "$SESSION" 2>/dev/null

tmux new-session -d -s "$SESSION" "php artisan serve"

tmux split-window -h -t "$SESSION" "php artisan horizon"

tmux split-window -v -t "$SESSION" "php artisan reverb:start"

tmux split-window -v -t "$SESSION" "php artisan app:monitor-proxmox"

tmux select-layout -t "$SESSION" tiled

tmux attach -t "$SESSION"
