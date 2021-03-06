<?php

namespace Modules\DisposableAirlines\Http\Controllers;

use App\Contracts\Controller;
use App\Events\PirepCancelled;
use App\Models\Aircraft;
use App\Models\Pirep;
use App\Models\Enums\AircraftState;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laracasts\Flash\Flash;
use Log;

class AdminController extends Controller
{
  // Fix Aircraft State Manually
  public function FixAircraftState($reg) {
    $result = 0;
    $aircraft = Aircraft::where('registration', $reg)->where('state', '!=', AircraftState::PARKED)->first();
    if($aircraft) {
      $pirep = Pirep::where('aircraft_id', $aircraft->id)->where('state', PirepState::IN_PROGRESS)->orderby('updated_at', 'desc')->first();
      if($pirep) {
        $pirep->state = PirepState::CANCELLED;
        $pirep->status = PirepStatus::CANCELLED;
        $pirep->notes = 'Cancelled By Admin';
        $pirep->save();
        $result = 1;
        event(new PirepCancelled($pirep));
        Log::info("Disposable Airlines Module: Pirep id=".$pirep->id." cancelled by Admin to fix aircraft state. Pirep State: CANCELLED");
      }
      $aircraft->state = AircraftState::PARKED;
      $aircraft->save();
      $result = $result + 1;
      Log::info("Disposable Airlines Module: Aircraft reg=".$aircraft->registration." was grounded by Admin. AC State: PARKED");
    }
    if($result === 0) { Flash::error('Nothing Done... Aircraft Not Found or was already PARKED'); }
    elseif($result === 1) { Flash::success('Aircraft State Changed Back to PARKED'); }
    elseif($result === 2) { Flash::success('Aircraft State Changed Back to PARKED and Pirep CANCELLED'); }
  }

  public function ChangeWebhookSettings($whmain, $whurl, $whname) {
    if($whmain != 'true') { $whmain = 'false'; }
    if(empty($whname)) { $whname = config('app.name'); }
    DB::table('disposable_settings')->upsert([
      ['key' => 'dairlines.discord_pirepmsg', 'value'=> $whmain],
      ['key' => 'dairlines.discord_pirep_webhook', 'value' => $whurl],
      ['key' => 'dairlines.discord_pirep_msgposter', 'value' => $whname],
    ],['key'], ['value']);
    Flash::success('Discord WebHook Settings Updated');
  }

  public function ChangeStateControlSettings($state_choice) {
    if($state_choice != 'true') { $state_choice = 'false'; }
    DB::table('disposable_settings')->upsert([
      ['key' => 'dairlines.acstate_control', 'value'=> $state_choice],
    ],['key'], ['value']);
    Flash::success('Aircraft State Control Settings Updated');
  }

  // Admin Page
  public function admin(Request $request)
  {
    $acreg = $request->input('parkac');
    if($acreg) {
      $this->FixAircraftState($acreg);
    }

    if($request->input('action') === 'pirepmsg') {
      $whmain = $request->input('mainsetting');
      $whurl = $request->input('webhookurl');
      $whname = $request->input('webhookname');
      $this->ChangeWebhookSettings($whmain, $whurl, $whname);
    }

    if($request->input('action') === 'acstate') {
      $state_choice = $request->input('sc');
      $this->ChangeStateControlSettings($state_choice);
    }

    return view('DisposableAirlines::admin');
  }
}
