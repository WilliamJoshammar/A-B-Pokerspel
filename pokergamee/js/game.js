// game.js - Spellogik, polling och chatt

let lastChatId = 0;

function poll() {
    fetchState();
    fetchChat();
}
setInterval(poll, 2000);
poll();

// ===== SPELSTATUS =====
function fetchState() {
    fetch('/pokerGame/api/game_state.php?room=' + ROOM_ID + '&user=' + MY_USER_ID)
        .then(r => r.json())
        .then(data => {
            if (data.error) return;
            renderState(data);
        });
}

function renderState(data) {
    document.getElementById('chip-count').textContent = data.my_chips ?? '?';
    document.getElementById('pot-amount').textContent = data.pot ?? 0;
    document.getElementById('phase-display').textContent = phaseLabel(data.phase);

    renderCards('community-cards', data.community_cards || []);
    renderCards('my-cards', data.my_cards || []);

    document.getElementById('hand-rank').textContent = data.hand_rank || '';

    renderSeats(data.players || []);

    const isMyTurn = data.current_player_id == MY_USER_ID
        && data.phase !== 'waiting'
        && data.phase !== 'showdown';

    document.getElementById('action-buttons').style.display = isMyTurn ? 'flex' : 'none';
    document.getElementById('waiting-msg').style.display   = isMyTurn ? 'none'  : 'block';

    if (isMyTurn) {
        const currentBet = data.current_bet || 0;
        const myBet      = data.my_bet || 0;
        const canCheck   = currentBet === 0 || currentBet === myBet;
        document.getElementById('btn-check').style.display = canCheck ? 'inline-block' : 'none';
        document.getElementById('btn-call').style.display  = canCheck ? 'none' : 'inline-block';
        document.getElementById('btn-call').textContent    = 'Call ' + (currentBet - myBet);
    }

    const canStart = data.player_count >= 2
        && data.phase === 'waiting'
        && data.is_first_player;
    document.getElementById('start-area').style.display = canStart ? 'block' : 'none';

    if (data.phase === 'showdown' && data.winner) {
        document.getElementById('phase-display').textContent =
            '🏆 ' + data.winner + ' vinner ' + data.pot + ' chips!';
    }
}

function phaseLabel(p) {
    const labels = {
        waiting:  'Väntar på spelare',
        preflop:  'Pre-flop',
        flop:     'Flop',
        turn:     'Turn',
        river:    'River',
        showdown: 'Showdown'
    };
    return labels[p] || p;
}

function renderCards(containerId, cards) {
    const el = document.getElementById(containerId);
    el.innerHTML = cards.map(c => {
        const red = (c.suit === '♥' || c.suit === '♦') ? ' red' : '';
        return '<div class="card' + red + '"><span>' + c.value + '</span><span>' + c.suit + '</span></div>';
    }).join('');
}

function renderSeats(players) {
    const container = document.getElementById('seats-container');
    container.innerHTML = players.map(function(p, i) {
        const isMe   = p.user_id == MY_USER_ID;
        const folded = p.has_folded ? ' folded' : '';
        const active = p.is_current ? ' active-player' : '';
        const pos    = getSeatPosition(i, players.length);
        return '<div class="seat' + folded + active + '" style="left:' + pos.x + '%;top:' + pos.y + '%">'
            + '<div class="seat-name">' + (isMe ? '⭐ ' : '') + p.username + '</div>'
            + '<div class="seat-chips">💰 ' + p.chips + '</div>'
            + (p.bet > 0 ? '<div class="seat-bet">Bet: ' + p.bet + '</div>' : '')
            + (p.has_folded ? '<div class="seat-status">Folded</div>' : '')
            + '</div>';
    }).join('');
}

function getSeatPosition(index, total) {
    const angle = (index / total) * 2 * Math.PI - Math.PI / 2;
    return {
        x: 50 + 38 * Math.cos(angle),
        y: 50 + 35 * Math.sin(angle)
    };
}

// ===== ACTIONS =====
function doAction(action) {
    const amount = parseInt(document.getElementById('raise-amount').value) || 0;
    fetch('/pokerGame/api/game_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'room=' + ROOM_ID + '&user=' + MY_USER_ID + '&action=' + action + '&amount=' + amount
    }).then(r => r.json()).then(fetchState);
}

function startGame() {
    fetch('/pokerGame/api/game_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'room=' + ROOM_ID + '&user=' + MY_USER_ID + '&action=start'
    }).then(r => r.json()).then(fetchState);
}
