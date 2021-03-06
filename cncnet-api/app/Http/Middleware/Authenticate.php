<?php namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;

class Authenticate {

	/**
	 * The Guard implementation.
	 *
	 * @var Guard
	 */
	protected $auth;

	/**
	 * Create a new filter instance.
	 *
	 * @param  Guard  $auth
	 * @return void
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
        $route = $request->route();
        $actions = $route->getAction();

		if ($this->auth->guest())
		{
			if ($request->ajax() || $request->wantsJson())
			{
				return response('Unauthorized.', 401);
			}
            else if (isset($actions["guestsAllowed"]))
            {
                return $next($request);
            }
			else
			{
				return redirect()->guest('auth/login');
			}
		}

        if (!$this->auth->user()->isGod())
        {
            $response = null;
            if (isset($actions["canEditAnyLadders"]))
            {
                if ($this->auth->user()->canEditAnyLadders() != $actions["canEditAnyLadders"])
                {
                    $response = response('Unauthorized.', 401);
                }
            }

            if ($request->ladderId !== null)
            {
                $ladder = \App\Ladder::find($request->ladderId);

                if (isset($actions["canAdminLadder"]))
                {
                    if ($actions["canAdminLadder"] != $this->auth->user()->isLadderAdmin($ladder))
                    {
                        $response = response('Unauthorized.', 401);
                    }
                }

                if (isset($actions["canModLadder"]))
                {
                    if ($actions["canModLadder"] != $this->auth->user()->isLadderMod($ladder))
                    {
                        $response = response('Unauthorized.', 401);
                    }
                }

                if (isset($actions["canTestLadder"]))
                {
                    if ($actions["canTestLadder"] != $this->auth->user()->isLadderTester($ladder))
                    {
                        $response = response('Unauthorized.', 401);
                    }
                }
            }

            if ($request->gameSchemaId !== null)
            {
                $gameSchema = \App\GameObjectSchema::find($request->gameSchemaId);

                if (isset($actions['objectSchemaManager']))
                {
                    $gameSchema->managers()->where('user_id', '=', $this->auth->user()->id);
                }
            }

            if ($response !== null)
            {
                return $response;
            }
        }

		return $next($request);
	}

}
