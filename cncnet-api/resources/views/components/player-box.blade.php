<a href="{{ $url or "/404"}}" class="player-box-link">
    <div class="player-box player-card {{ $playerCard or "carville"}}">
        <div class="details text-left">
            <div class="player-badge {{ $badge or "" }}"></div>
            <h1 class="rank">Rank #{{ $rank or "Unranked" }}</h1>
            <p class="username">{{ $username or "" }}</p>
            <p class="points">Points {{ $points or "-1" }}</p>
            <ul class="list-unstyled extra-stats">
                <li>
                    Wins <i class="fa fa-level-up fa-fw fa-lg"></i> {{ $wins }}
                </li>
                <li>
                   Games <i class="fa fa-diamond fa-fw fa-lg"></i> {{ $totalGames }}
                </li>
            </ul>
        </div>
    </div>
</a>