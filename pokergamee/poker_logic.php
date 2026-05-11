<?php
// poker_logic - Kortlogik och handvärdering

function createDeck() {
    $suits = ['♠','♥','♦','♣'];
    $values = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($values as $val) {
            $deck[] = ['suit' => $suit, 'value' => $val];
        }
    }
    shuffle($deck);
    return $deck;
}

function cardNumericValue($val) {
    $map = ['2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,
            '9'=>9,'10'=>10,'J'=>11,'Q'=>12,'K'=>13,'A'=>14];
    return $map[$val] ?? 0;
}

function isRed($suit) {
    return in_array($suit, ['♥','♦']);
}

// Enkel handvärdering – returnerar [rank, name]
function evaluateHand($holeCards, $communityCards) {
    $all = array_merge($holeCards, $communityCards);
    if (count($all) < 2) return [0, 'Ingen hand'];

    $values = array_map(fn($c) => cardNumericValue($c['value']), $all);
    $suits  = array_map(fn($c) => $c['suit'], $all);
    sort($values);

    $valCounts = array_count_values($values);
    arsort($valCounts);
    $counts = array_values($valCounts);

    $isFlush = count(array_unique($suits)) === 1 && count($all) >= 5;
    $isStraight = false;
    if (count($all) >= 5) {
        $uniq = array_unique($values);
        sort($uniq);
        for ($i = 0; $i <= count($uniq) - 5; $i++) {
            if ($uniq[$i+4] - $uniq[$i] === 4 && count(array_unique(array_slice($uniq,$i,5))) === 5) {
                $isStraight = true;
            }
        }
    }

    if ($isFlush && $isStraight) return [8, 'Straight Flush'];
    if ($counts[0] === 4)        return [7, 'Fyrtal'];
    if ($counts[0] === 3 && isset($counts[1]) && $counts[1] >= 2) return [6, 'Kåk'];
    if ($isFlush)                return [5, 'Färg'];
    if ($isStraight)             return [4, 'Stege'];
    if ($counts[0] === 3)        return [3, 'Triss'];
    if ($counts[0] === 2 && isset($counts[1]) && $counts[1] === 2) return [2, 'Två par'];
    if ($counts[0] === 2)        return [1, 'Ett par'];
    return [0, 'Högsta kort'];
}

function dealCards(&$deck, $n) {
    return array_splice($deck, 0, $n);
}
?>
