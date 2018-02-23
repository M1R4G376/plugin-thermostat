<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class thermostat extends eqLogic {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	public static function pull($_options) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat)) {
			$cron = cron::byClassAndFunction('thermostat', 'pull', $_options);
			if (is_object($cron)) {
				$cron->remove();
			}
			throw new Exception('Thermostat ID non trouvé : ' . $_options['thermostat_id'] . '. Tache supprimé');
		}
		if ($thermostat->getConfiguration('engine', 'temporal') != 'temporal') {
			$cron = cron::byClassAndFunction('thermostat', 'pull', $_options);
			if (is_object($cron)) {
				$cron->remove();
			}
			return;
		}
		if (isset($_options['stop']) && $_options['stop'] == 1) {
			$status = $thermostat->getCmd(null, 'status')->execCmd();
			if ($status == __('Suspendu', __FILE__)) {
				return;
			}
			$thermostat->stopThermostat();
			$cron = cron::byClassAndFunction('thermostat', 'pull', $_options);
			if (is_object($cron)) {
				$cron->remove(true);
			}
		} elseif (isset($_options['smartThermostat']) && $_options['smartThermostat'] == 1) {
			log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Thermostat::pull => mode smart : ' . print_r($_options, true));
			$cron = cron::byClassAndFunction('thermostat', 'pull', $_options);
			if (is_object($cron)) {
				$cron->remove(false);
			}
			if (isset($_options['calendar_id'])) {
				$calendar = calendar::byId($_options['calendar_id']);
				if (is_object($calendar)) {
					$stateCalendar = $calendar->getCmd(null, 'state');
					if ($calendar->getIsEnable() == 0 || (is_object($stateCalendar) && $stateCalendar->execCmd() != 1)) {
						return;
					}
				}
			}
			if ($thermostat->getConfiguration('smart_start') == 1) {
				log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : next info : ' . print_r($_options['next'], true));
				if ($_options['next']['type'] == 'thermostat') {
					log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Type thermostat envoi de la consigne : ' . $_options['next']['consigne']);
					$cmd = $thermostat->getCmd(null, 'thermostat');
					$cmd->execCmd(array('slider' => $_options['next']['consigne']));
				} else if ($_options['next']['type'] == 'mode' && isset($_options['next']['cmd'])) {
					$mode = cmd::byId($_options['next']['cmd']);
					if (is_object($mode)) {
						log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Type mode envoi de la commande : ' . $_options['next']['cmd']);
						$mode->execCmd();
					}
				}
			}
		} else {
			self::temporal($_options);
		}
	}

	public static function hysteresis($_options) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat)) {
			return;
		}
		log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Lancement du calcul d\'hysteresis');
		$status = $thermostat->getCmd(null, 'status')->execCmd();
		if ($status == __('Suspendu', __FILE__)) {
			log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Thermostat suspendu je ne fais rien');
			return;
		}
		if ($thermostat->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__)) {
			log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Thermostat arrêté je ne fais rien');
			if ($status != __('Arrêté', __FILE__)) {
				$thermostat->stopThermostat();
			}
			return;
		}
		$cmd = $thermostat->getCmd(null, 'temperature');
		$temp = $cmd->execCmd();
		if ($cmd->getCollectDate() != '' && $cmd->getCollectDate() < date('Y-m-d H:i:s', strtotime('-' . $thermostat->getConfiguration('maxTimeUpdateTemp') . ' minutes' . date('Y-m-d H:i:s')))) {
			$thermostat->stopThermostat();
			log::add('thermostat', 'error', $thermostat->getHumanName() . __(' : Attention il n\'y a pas eu de mise à jour de la température depuis plus de : ', __FILE__) . $thermostat->getConfiguration('maxTimeUpdateTemp') . __(' min', __FILE__) . ' (' . $cmd->getCollectDate() . ')');
			return;
		}
		$consigne = $thermostat->getCmd(null, 'order')->execCmd();
		$thermostat->getCmd(null, 'order')->addHistoryValue($consigne);
		$hysteresis_low = $consigne - $thermostat->getConfiguration('hysteresis_threshold', 1);
		$hysteresis_hight = $consigne + $thermostat->getConfiguration('hysteresis_threshold', 1);
		log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Calcul => consigne : ' . $consigne . ' hysteresis_low : ' . $hysteresis_low . ' hysteresis_hight : ' . $hysteresis_hight . ' temp : ' . $temp . ' etat precedent : ' . $thermostat->getConfiguration('lastState'));
		$action = 'none';
		if ($temp < $hysteresis_low) {
			$action = 'heat';
		}
		if ($temp > $hysteresis_hight) {
			$action = 'cool';
		}
		if ($action == 'heat' && $thermostat->getConfiguration('lastState') == 'cool' && ($consigne - 2 * $thermostat->getConfiguration('hysteresis_threshold', 1)) < $temp) {
			$action = 'none';
		}
		if ($action == 'cool' && $thermostat->getConfiguration('lastState') == 'heat' && ($consigne + 2 * $thermostat->getConfiguration('hysteresis_threshold', 1)) > $temp) {
			$action = 'none';
		}
		if ($status == __('Chauffage', __FILE__) && $temp > $hysteresis_hight) {
			$action = 'stop';
		}
		if ($status == __('Climatisation', __FILE__) && $temp < $hysteresis_low) {
			$action = 'stop';
		}

		if ($action == 'heat') {
			if ($status != __('Chauffage', __FILE__)) {
				log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Je dois chauffer');
				$thermostat->heat();
			}
		} elseif ($action == 'cool') {
			if ($status != __('Climatisation', __FILE__)) {
				log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Je dois refroidir');
				$thermostat->cool();
			}
		} elseif ($action == 'stop') {
			if ($status != __('Arrêté', __FILE__)) {
				log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Je m\'arrete');
				$thermostat->stopThermostat();
			}
		}
	}

	public static function temporal($_options) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat)) {
			return;
		}
		log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Debut calcul temporel');
		$thermostat->reschedule(date('Y-m-d H:i:00', strtotime('+' . $thermostat->getConfiguration('cycle') . ' min ' . date('Y-m-d H:i:00'))));
		log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Reprogrammation automatique : ' . date('Y-m-d H:i:s', strtotime('+' . $thermostat->getConfiguration('cycle') . ' min ' . date('Y-m-d H:i:00'))));
		$status = $thermostat->getCmd(null, 'status')->execCmd();
		if ($status == __('Suspendu', __FILE__)) {
			log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Thermostat suspendu');
			return;
		}
		if ($thermostat->getConfiguration('smart_start') == 1) {
			log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Smart schedule');
			$thermostat->getNextState();
			log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Smart start end');
		}
		$mode = $thermostat->getCmd(null, 'mode')->execCmd();
		if ($mode == 'Off') {
			log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Thermostat sur off');
			if ($status != __('Arrêté', __FILE__)) {
				$thermostat->stopThermostat();
			}
			return;
		}
		$cmd = $thermostat->getCmd(null, 'temperature');
		$temp_in = $cmd->execCmd();
		if ($cmd->getCollectDate() != '' && $cmd->getCollectDate() < date('Y-m-d H:i:s', strtotime('-' . $thermostat->getConfiguration('maxTimeUpdateTemp') . ' minutes' . date('Y-m-d H:i:s')))) {
			$thermostat->stopThermostat();
			if ($thermostat->getCache('probe_failure', 0) == 0) {
				log::add('thermostat', 'error', $thermostat->getHumanName() . __(' : Attention, défaillance de la sonde de température, il n\'y a pas eu de mise à jour de la température depuis : ', __FILE__) . $thermostat->getConfiguration('maxTimeUpdateTemp') . ' min (' . $cmd->getCollectDate() . ').' . __('Thermostat mis en sécurité', __FILE__));
			}
			$thermostat->setCache('probe_failure', 1);
			return;
		}
		$temp_out = $thermostat->getCmd(null, 'temperature_outdoor')->execCmd();
		if (!is_numeric($temp_in)) {
			if ($thermostat->getCache('probe_failure', 0) == 0) {
				log::add('thermostat', 'error', $thermostat->getHumanName() . ' : La température intérieur n\'est pas un numérique');
			}
			$thermostat->setCache('probe_failure', 1);
			return;
		}
		$thermostat->setCache('probe_failure', 0);
		if (($temp_in < ($thermostat->getConfiguration('lastOrder') - $thermostat->getConfiguration('offsetHeatFaillure', 1)) && $temp_in < $thermostat->getConfiguration('lastTempIn') && $thermostat->getConfiguration('lastState') == 'heat' && $thermostat->getConfiguration('coeff_indoor_heat_autolearn') > 25) ||
			($temp_in > ($thermostat->getConfiguration('lastOrder') + $thermostat->getConfiguration('offsetColdFaillure', 1)) && $temp_in > $thermostat->getConfiguration('lastTempIn') && $thermostat->getConfiguration('lastState') == 'cool' && $thermostat->getConfiguration('coeff_indoor_cool_autolearn') > 25)) {

			$thermostat->setConfiguration('nbConsecutiveFaillure', $thermostat->getConfiguration('nbConsecutiveFaillure') + 1);
			if ($thermostat->getConfiguration('nbConsecutiveFaillure', 0) == 2) {
				log::add('thermostat', 'error', $thermostat->getHumanName() . ' : Attention une défaillance du chauffage est détectée');
				$thermostat->failureActuator();
			}
		} else {
			$thermostat->setConfiguration('nbConsecutiveFaillure', 0);
		}
		if ($thermostat->getConfiguration('nbConsecutiveFaillure', 0) < 3 && $thermostat->getConfiguration('autolearn') == 1 && strtotime($thermostat->getConfiguration('endDate')) < strtotime('now')) {
			log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Begin auto learning');
			if ($thermostat->getConfiguration('last_power') < 100 && $thermostat->getConfiguration('last_power') > 0) {
				log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Last power ok, check what I have to learn, last state : ' . $thermostat->getConfiguration('lastState'));
				$learn_outdoor = false;
				if ($thermostat->getConfiguration('lastState') == 'heat') {
					log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Last state is heat');
					if ($temp_in > $thermostat->getConfiguration('lastTempIn') && $thermostat->getConfiguration('lastOrder') > $thermostat->getConfiguration('lastTempIn')) {
						log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Last temps in < at current temp in');
						$coeff_indoor_heat = $thermostat->getConfiguration('coeff_indoor_heat') * (($thermostat->getConfiguration('lastOrder') - $thermostat->getConfiguration('lastTempIn')) / ($temp_in - $thermostat->getConfiguration('lastTempIn')));
						$coeff_indoor_heat = ($thermostat->getConfiguration('coeff_indoor_heat') * $thermostat->getConfiguration('coeff_indoor_heat_autolearn') + $coeff_indoor_heat) / ($thermostat->getConfiguration('coeff_indoor_heat_autolearn') + 1);
						$thermostat->setConfiguration('coeff_indoor_heat_autolearn', min($thermostat->getConfiguration('coeff_indoor_heat_autolearn') + 1, 50));
						if ($coeff_indoor_heat < 0) {
							$coeff_indoor_heat = 0;
						}
						$thermostat->setConfiguration('coeff_indoor_heat', round($coeff_indoor_heat, 2));
						log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : New coeff heat indoor : ' . $coeff_indoor_heat);
					} else if ($temp_out < $thermostat->getConfiguration('lastOrder')) {
						log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Learn outdoor heat');
						$coeff_in = $thermostat->getConfiguration('coeff_indoor_heat');
						$coeff_outdoor = $coeff_in * (($thermostat->getConfiguration('lastOrder') - $temp_in) / ($thermostat->getConfiguration('lastOrder') - $temp_out)) + $thermostat->getConfiguration('coeff_outdoor_heat');
						$coeff_outdoor = ($thermostat->getConfiguration('coeff_outdoor_heat') * $thermostat->getConfiguration('coeff_outdoor_heat_autolearn') + $coeff_outdoor) / ($thermostat->getConfiguration('coeff_outdoor_heat_autolearn') + 1);
						$thermostat->setConfiguration('coeff_outdoor_heat_autolearn', min($thermostat->getConfiguration('coeff_outdoor_heat_autolearn') + 1, 50));
						if ($coeff_outdoor < 0) {
							$coeff_outdoor = 0;
						}
						$thermostat->setConfiguration('coeff_outdoor_heat', round($coeff_outdoor, 2));
						log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : New coeff outdoor heat: ' . $coeff_outdoor);
					}
				}

				if ($thermostat->getConfiguration('lastState') == 'cool') {
					log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Last state is cool');
					if ($temp_in <= $thermostat->getConfiguration('lastTempIn') && $thermostat->getConfiguration('lastOrder') < $thermostat->getConfiguration('lastTempIn')) {
						log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Last temps in > at current temp in');
						$coeff_indoor_cool = $thermostat->getConfiguration('coeff_indoor_cool') * (($thermostat->getConfiguration('lastTempIn') - $thermostat->getConfiguration('lastOrder')) / ($thermostat->getConfiguration('lastTempIn') - $temp_in));
						$coeff_indoor_cool = ($thermostat->getConfiguration('coeff_indoor_cool') * $thermostat->getConfiguration('coeff_indoor_cool_autolearn') + $coeff_indoor_cool) / ($thermostat->getConfiguration('coeff_indoor_cool_autolearn') + 1);
						$thermostat->setConfiguration('coeff_indoor_cool_autolearn', min($thermostat->getConfiguration('coeff_indoor_cool_autolearn') + 1, 50));
						if ($coeff_indoor_cool < 0) {
							$coeff_indoor_cool = 0;
						}
						$thermostat->setConfiguration('coeff_indoor_cool', round($coeff_indoor_cool, 2));
						log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : New coeff cool indoor : ' . $coeff_indoor_cool);
					} else if ($temp_out < $thermostat->getConfiguration('lastOrder')) {
						log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Learn outdoor cool');
						$coeff_in = $thermostat->getConfiguration('coeff_indoor_cool');
						$coeff_outdoor = $coeff_in * (($thermostat->getConfiguration('lastOrder') - $temp_in) / ($thermostat->getConfiguration('lastOrder') - $temp_out)) + $thermostat->getConfiguration('coeff_outdoor_cool');
						$coeff_outdoor = ($thermostat->getConfiguration('coeff_outdoor_cool') * $thermostat->getConfiguration('coeff_outdoor_autolearn') + $coeff_outdoor) / ($thermostat->getConfiguration('coeff_outdoor_cool_autolearn') + 1);
						$thermostat->setConfiguration('coeff_outdoor_cool_autolearn', min($thermostat->getConfiguration('coeff_outdoor_cool_autolearn') + 1, 50));
						if ($coeff_outdoor < 0) {
							$coeff_outdoor = 0;
						}
						$thermostat->setConfiguration('coeff_outdoor_cool', round($coeff_outdoor, 2));
						log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : New coeff outdoor cool : ' . $coeff_outdoor);
					}
				}
			}
		}

		$consigne = $thermostat->getCmd(null, 'order')->execCmd();
		$temporal_data = $thermostat->calculTemporalData($consigne);
		$thermostat->setConfiguration('last_power', $temporal_data['power']);
		$cycle = jeedom::evaluateExpression($thermostat->getConfiguration('cycle'));
		$duration = ($temporal_data['power'] * $cycle) / 100;
		$thermostat->setConfiguration('lastOrder', $consigne);
		$thermostat->setConfiguration('lastTempIn', $temp_in);
		$thermostat->setConfiguration('lastTempOut', $temp_out);
		$thermostat->setConfiguration('endDate', date('Y-m-d H:i:s', strtotime('+' . ceil($cycle * 0.9) . ' min ' . date('Y-m-d H:i:s'))));
		log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Cycle duration : ' . $duration);
		if ($temporal_data['power'] < $thermostat->getConfiguration('minCycleDuration', 5)) {
			log::add('thermostat', 'debug', $thermostat->getHumanName() . ' : Durée du cycle trop courte, aucun lancement');
			$thermostat->setConfiguration('lastState', 'stop');
			$thermostat->stopThermostat();
			$thermostat->save();
			return;
		}
		if ($duration > 0 && $duration < $cycle) {
			$thermostat->reschedule(date('Y-m-d H:i:s', strtotime('+' . round($duration) . ' min ' . date('Y-m-d H:i:s'))), true);
		}
		if ($duration >= $cycle) {
			$thermostat->reschedule(null, true);
		}

		if ($thermostat->getConfiguration('lastState') == 'heat' && $temporal_data['direction'] < 0) {
			$thermostat->setConfiguration('lastState', 'stop');
			$thermostat->stopThermostat();
		}
		if ($thermostat->getConfiguration('lastState') == 'cool' && $temporal_data['direction'] > 0) {
			$thermostat->setConfiguration('lastState', 'stop');
			$thermostat->stopThermostat();
		}
		$thermostat->save();
		if ($duration > 0) {
			if ($temporal_data['direction'] > 0) {
				if ($thermostat->heat()) {
					$thermostat->getCmd(null, 'power')->event($temporal_data['power']);
				}
			} else {
				if ($thermostat->cool()) {
					$thermostat->getCmd(null, 'power')->event($temporal_data['power']);
				}
			}
		}
	}

	public static function cron() {
		foreach (thermostat::byType('thermostat', true) as $thermostat) {
			if ($thermostat->getConfiguration('repeat_commande_cron') != '') {
				try {
					$c = new Cron\CronExpression($thermostat->getConfiguration('repeat_commande_cron'), new Cron\FieldFactory);
					if ($c->isDue()) {
						switch ($thermostat->getCmd(null, 'status')->execCmd()) {
							case __('Chauffage', __FILE__):
								$thermostat->heat(true);
								break;
							case __('Arrêté', __FILE__):
								$thermostat->stopThermostat(true);
								break;
							case __('Climatisation', __FILE__):
								$thermostat->cool(true);
								break;
						}
					}
				} catch (Exception $e) {
					log::add('thermostat', 'error', $thermostat->getHumanName() . ' : ' . $e->getMessage());
				}
			}
			if ($thermostat->getConfiguration('engine', 'temporal') == 'temporal' && date('i') % 10 == 0) {
				$cron = cron::byClassAndFunction('thermostat', 'pull', array('thermostat_id' => intval($thermostat->getId())));
				if (!is_object($cron)) {
					$thermostat->reschedule(date('Y-m-d H:i:s', strtotime('+2 min ' . date('Y-m-d H:i:s'))));
				} else {
					if ($cron->getState() != 'run') {
						try {
							$c = new Cron\CronExpression($cron->getSchedule(), new Cron\FieldFactory);
							if (!$c->isDue()) {
								$c->getNextRunDate();
							}
						} catch (Exception $ex) {
							$thermostat->reschedule(date('Y-m-d H:i:s', strtotime('+2 min ' . date('Y-m-d H:i:s'))));
						}
					}
				}
			}
			if ($thermostat->getConfiguration('engine', 'temporal') == 'hysteresis' && $thermostat->getConfiguration('hysteresis_cron') != '') {
				try {
					$c = new Cron\CronExpression($thermostat->getConfiguration('hysteresis_cron'), new Cron\FieldFactory);
					if ($c->isDue()) {
						$thermostat->getCmd(null, 'temperature')->event(jeedom::evaluateExpression($thermostat->getConfiguration('temperature_indoor')));
						thermostat::hysteresis(array('thermostat_id' => $thermostat->getId()));
					}
				} catch (Exception $e) {
					log::add('thermostat', 'error', $thermostat->getHumanName() . ' : ' . $e->getMessage());
				}
			}

			if (strtolower($thermostat->getCmd(null, 'mode')->execCmd()) == 'off') {
				continue;
			}
			$temperature = $thermostat->getCmd(null, 'temperature');
			$temp_in = $temperature->execCmd();
			$failure = false;
			if ($thermostat->getConfiguration('maxTimeUpdateTemp') != '') {
				if ($temperature->getCollectDate() != '' && strtotime($temperature->getCollectDate()) < strtotime('-' . $thermostat->getConfiguration('maxTimeUpdateTemp') . ' minutes' . date('Y-m-d H:i:s'))) {
					$thermostat->failure($thermostat->getConfiguration('maxTimeUpdateTemp', 5));
					if ($thermostat->getCache('temp_threshold', 0) == 0) {
						log::add('thermostat', 'error', $thermostat->getHumanName() . __(' : Attention il n\'y a pas eu de mise à jour de la température depuis : ', __FILE__) . $thermostat->getConfiguration('maxTimeUpdateTemp') . '(' . $temperature->getCollectDate() . ')');
					}
					$failure = true;
				}
			}
			if ($thermostat->getConfiguration('temperature_indoor_min') != '' && is_numeric($thermostat->getConfiguration('temperature_indoor_min')) && $thermostat->getConfiguration('temperature_indoor_min') > $temp_in) {
				$thermostat->failure($thermostat->getConfiguration('maxTimeUpdateTemp', 5));
				if ($thermostat->getCache('temp_threshold', 0) == 0) {
					log::add('thermostat', 'error', $thermostat->getHumanName() . __(' : Attention la température intérieure est en dessous du seuil autorisé : ', __FILE__) . $temp_in);
				}
				$failure = true;
			}
			if ($thermostat->getConfiguration('temperature_indoor_max') != '' && is_numeric($thermostat->getConfiguration('temperature_indoor_max')) && $thermostat->getConfiguration('temperature_indoor_max') < $temp_in) {
				$thermostat->failure($thermostat->getConfiguration('maxTimeUpdateTemp', 5));
				if ($thermostat->getCache('temp_threshold', 0) == 0) {
					log::add('thermostat', 'error', $thermostat->getHumanName() . __(' : Attention la température intérieure est au dessus du seuil autorisé : ', __FILE__) . $temp_in);
				}
				$failure = true;
			}
			if (!$failure) {
				$thermostat->setCache('temp_threshold', 0);
			} else {
				$thermostat->setCache('temp_threshold', 1);
			}
		}

	}

	public static function start() {
		foreach (thermostat::byType('thermostat', true) as $thermostat) {
			if (strtolower($thermostat->getCmd(null, 'mode')->execCmd()) == 'off') {
				continue;
			}
			$thermostat->stopThermostat();
			if ($thermostat->getConfiguration('engine', 'temporal') == 'temporal') {
				thermostat::temporal(array('thermostat_id' => $thermostat->getId()));
			} else if ($thermostat->getConfiguration('engine', 'temporal') == 'hysteresis') {
				thermostat::hysteresis(array('thermostat_id' => $thermostat->getId()));
			}
		}
	}

	public static function window($_option) {
		log::add('thermostat', 'debug', 'Detection d\'un changement d\'une fenetre');
		$thermostat = thermostat::byId($_option['thermostat_id']);
		if (is_object($thermostat) && $thermostat->getIsEnable() == 1) {
			$windows = $thermostat->getConfiguration('window');
			foreach ($windows as $window) {
				if ('#' . $_option['event_id'] . '#' == $window['cmd']) {
					if (isset($window['invert']) && $window['invert'] == 1) {
						$_option['value'] = ($_option['value'] == 0) ? 1 : 0;
					}
					log::add('thermostat', 'debug', 'Fenetre trouvée : ' . $window['cmd'] . ' valeur : ' . $_option['value']);
					if ($_option['value'] == 0) {
						log::add('thermostat', 'debug', 'Fenetre fermée');
						$thermostat->windowClose($window);
					} else {
						log::add('thermostat', 'debug', 'Fenetre ouverte');
						$thermostat->windowOpen($window);
					}
				}
			}
		}
	}

/*     * *********************Methode d'instance************************* */

	public function windowClose($_window) {
		if ($this->getCache('window::state::' . str_replace('#', '', $_window['cmd']), 0) != 1) {
			log::add('thermostat', 'debug', $this->getHumanName() . '[windowClose] Je n\'ai jamais vu cette fenete ouverte, je ne fais rien');
			return;
		}
		$this->setCache('window::state::' . str_replace('#', '', $_window['cmd']), 0);
		log::add('thermostat', 'debug', '[windowClose] => ' . json_encode($_window));
		if ($this->getCmd(null, 'status')->execCmd() != __('Suspendu', __FILE__)) {
			log::add('thermostat', 'debug', $this->getHumanName() . '[windowClose] Thermostat non suspendu je ne fais rien');
			return;
		}
		$this->setCache('window::close::' . str_replace('#', '', $_window['cmd']) . '::datetime', date('Y-m-d H:i:s'));
		$restartTime = (isset($_window['restartTime']) && $_window['restartTime'] != '') ? $_window['restartTime'] * 60 : 0;
		if (is_numeric($restartTime) && $restartTime > 0) {
			log::add('thermostat', 'debug', $this->getHumanName() . '[windowClose] Pause de ' . $restartTime . 's');
			sleep($restartTime);
		}
		$windows = $this->getConfiguration('window');
		foreach ($windows as $window) {
			$cmd = cmd::byId(str_replace('#', '', $window['cmd']));
			if (!is_object($cmd)) {
				continue;
			}
			$value = $cmd->execCmd();
			if (isset($window['invert']) && $window['invert'] == 1) {
				$value = ($value == 0) ? 1 : 0;
			}
			if ($value == 1) {
				log::add('thermostat', 'debug', $this->getHumanName() . '[windowClose] Fenêtre ouverte, je ne fais rien : ' . $window['cmd']);
				return;
			}
			$restartTime = (isset($window['restartTime']) && $window['restartTime'] != '') ? $window['restartTime'] * 60 : 0;
			if ((strtotime($this->getCache('window::close::' . $cmd->getId() . '::datetime')) + $restartTime - 1) > strtotime('now')) {
				log::add('thermostat', 'debug', $this->getHumanName() . '[windowClose] Fenêtre fermée depuis trop peu de temps, je ne fais rien : ' . $window['cmd'] . ' => ' . $this->getCache('window::close::' . $cmd->getId() . '::datetime') . '+' . $restartTime . 's');
				return;
			}
		}
		log::add('thermostat', 'debug', $this->getHumanName() . '[windowClose] Toute les fenêtres sont fermées, je relance le chauffage');
		$this->getCmd(null, 'status')->event(__('Calcul', __FILE__));
		if ($this->getConfiguration('engine', 'temporal') == 'temporal') {
			thermostat::temporal(array('thermostat_id' => $this->getId()));
		} else if ($this->getConfiguration('engine', 'temporal') == 'hysteresis') {
			thermostat::hysteresis(array('thermostat_id' => $this->getId()));
		}
	}

	public function windowOpen($_window) {
		log::add('thermostat', 'debug', '[windowOpen] => ' . json_encode($_window));
		$this->setCache('window::state::' . str_replace('#', '', $_window['cmd']), 1);
		if ($this->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
			log::add('thermostat', 'debug', $this->getHumanName() . '[windowOpen] Thermostat arreté ou suspendu je ne fais rien');
			return;
		}
		$stopTime = (isset($_window['stopTime']) && $_window['stopTime'] != '') ? $_window['stopTime'] : 0;
		if (is_numeric($stopTime) && $stopTime > 0) {
			log::add('thermostat', 'debug', $this->getHumanName() . '[windowOpen] Pause de ' . $stopTime . 'min');
			sleep($stopTime * 60);
		}
		$cmd = cmd::byId(str_replace('#', '', $_window['cmd']));
		if (!is_object($cmd)) {
			log::add('thermostat', 'debug', $this->getHumanName() . '[windowOpen] Commande introuvable je ne fais rien');
			return;
		}
		$value = $cmd->execCmd();
		if (isset($_window['invert']) && $_window['invert'] == 1) {
			$value = ($value == 0) ? 1 : 0;
		}
		log::add('thermostat', 'debug', $this->getHumanName() . '[windowOpen] Valeur commande : ' . $value);
		if ($value == 1) {
			log::add('thermostat', 'debug', $this->getHumanName() . '[windowOpen] Arret du thermostat');
			$this->stopThermostat();
			$this->getCmd(null, 'status')->event(__('Suspendu', __FILE__));
		}
		return true;
	}

	public function reschedule($_next = null, $_stop = false, $_smartThermostat = false) {
		$options = array('thermostat_id' => intval($this->getId()));
		if ($_stop) {
			$options['stop'] = intval(1);
		}
		if ($_smartThermostat !== false) {
			$crons = cron::searchClassAndFunction('thermostat', 'pull', '"thermostat_id":' . intval($this->getId()) . '%"smartThermostat":1');
			if (is_array($crons) && count($crons)) {
				foreach ($crons as $cron) {
					$cron->remove();
				}
			}
			$options['smartThermostat'] = intval(1);
			$options['next'] = $_smartThermostat;
		}
		$cron = cron::byClassAndFunction('thermostat', 'pull', $options);
		if ($_next == null) {
			if (is_object($cron)) {
				$cron->remove();
			}
			return;
		}
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('thermostat');
			$cron->setFunction('pull');
			$cron->setOption($options);
		}
		$_next = strtotime($_next);
		$cron->setTimeout($this->getConfiguration('cycle', 60) + 10);
		$cron->setSchedule(cron::convertDateToCron($_next));
		$cron->setOnce(1);
		$cron->save();

	}

	public function calculTemporalData($_consigne, $_allowOverfull = false) {
		$temp_out = $this->getCmd(null, 'temperature_outdoor')->execCmd();
		$temp_in = $this->getCmd(null, 'temperature')->execCmd();
		if (!is_numeric($temp_out)) {
			log::add('thermostat', 'debug', $this->getHumanName() . ' : Attention température exterieur erroné : ' . $temp_out);
			$temp_out = $_consigne;
		}

		log::add('thermostat', 'debug', $this->getHumanName() . ' : Temp in : ' . $temp_in . ' - Temp out : ' . $temp_out . ' - Consigne : ' . $_consigne);
		$diff_in = $_consigne - $temp_in;
		$diff_out = $_consigne - $temp_out;
		$direction = ($_consigne > $temp_in) ? +1 : -1;
		if ($direction < 0 && (($temp_in < ($_consigne + 0.5) && $this->getConfiguration('lastState') == 'heat') || $temp_out < $_consigne)) {
			$direction = +1;
		}
		if ($direction > 0 && (($temp_in > ($_consigne - 0.5) && $this->getConfiguration('lastState') == 'cool') || $temp_out > $_consigne)) {
			$direction = -1;
		}
		log::add('thermostat', 'debug', $this->getHumanName() . ' : Direction : ' . $direction);
		if ($temp_in >= ($_consigne + 1.5) && $direction == 1) {
			if ($this->getCache('temp_threshold', 0) == 0) {
				log::add('thermostat', 'debug', $this->getHumanName() . ' : La temperature est supérieure à la consigne de plus de 1.5°C je ne fais rien');
			}
			$this->setCache('temp_threshold', 1);
			return array('power' => 0, 'direction' => $direction);
		}
		if ($temp_in <= ($_consigne - 1.5) && $direction == -1) {
			if ($this->getCache('temp_threshold', 0) == 0) {
				log::add('thermostat', 'debug', $this->getHumanName() . ' : La temperature est inférieure à la consigne de plus de 1.5°C je ne fais rien');
			}
			$this->setCache('temp_threshold', 1);
			return array('power' => 0, 'direction' => $direction);
		}
		$this->setCache('temp_threshold', 0);
		$coeff_out = ($direction > 0) ? $this->getConfiguration('coeff_outdoor_heat') : $this->getConfiguration('coeff_outdoor_cool');
		$coeff_in = ($direction > 0) ? $this->getConfiguration('coeff_indoor_heat') : $this->getConfiguration('coeff_indoor_cool');
		$offset = ($direction > 0) ? $this->getConfiguration('offset_heat') : $this->getConfiguration('offset_cool');
		$power = ($direction * $diff_in * $coeff_in) + ($direction * $diff_out * $coeff_out) + $offset;
		log::add('thermostat', 'debug', $this->getHumanName() . ' : Power calcul : (' . $diff_in . ' * ' . $coeff_in . ') + (' . $diff_out . ' * ' . $coeff_out . ') + ' . $offset);
		if ($power > 100 && !$_allowOverfull) {
			$power = 100;
		}
		if ($power < 0) {
			$power = 0;
		}
		return array('power' => $power, 'direction' => $direction);
	}

	public function getNextState() {
		if ($this->getConfiguration('engine', 'temporal') != 'temporal') {
			return '';
		}
		try {
			$plugin = plugin::byId('calendar');
			if (!is_object($plugin) || $plugin->isActive() != 1) {
				return '';
			}
		} catch (Exception $ex) {
			log::add('thermostat', 'debug', $this->getHumanName() . ' : Plugin agenda non détecté');
			return '';
		}
		if (!class_exists('calendar_event')) {
			return '';
		}
		log::add('thermostat', 'debug', $this->getHumanName() . ' : Plugin agenda detecté');

		$thermostat = $this->getCmd(null, 'thermostat');
		$next = null;
		$position = null;
		foreach ($this->getCmd(null, 'modeAction', null, true) as $mode) {
			$events = calendar_event::searchByCmd($mode->getId());
			if (is_array($events) && count($events) > 0) {
				foreach ($events as $event) {
					$calendar = $event->getEqLogic();
					$stateCalendar = $calendar->getCmd(null, 'state');
					if ($calendar->getIsEnable() == 0 || (is_object($stateCalendar) && $stateCalendar->execCmd() != 1)) {
						continue;
					}
					foreach ($event->getCmd_param('start') as $action) {
						if ($action['cmd'] == '#' . $mode->getId() . '#') {
							$position = 'start';
						}
					}
					foreach ($event->getCmd_param('end') as $action) {
						if ($action['cmd'] == '#' . $mode->getId() . '#') {
							if ($position == 'start') {
								$position = null;
							} else {
								$position = 'end';
							}
						}
					}
					$nextOccurence = $event->nextOccurrence($position, true);
					if ($nextOccurence['date'] != '' && ($next == null || (strtotime($next['date']) > strtotime($nextOccurence['date']) && strtotime($nextOccurence['date']) > (strtotime('now') + 120)))) {
						$consigne = 0;
						foreach ($this->getConfiguration('existingMode') as $existingMode) {
							if ($mode->getName() == $existingMode['name']) {
								foreach ($existingMode['actions'] as $action) {
									if ('#' . $thermostat->getId() . '#' == $action['cmd']) {
										$consigne = $action['options']['slider'];
									}
								}
							}
						}
						if ($consigne != 0) {
							$next = array(
								'date' => $nextOccurence['date'],
								'event' => $event,
								'consigne' => $consigne,
								'calendar_id' => $calendar->getId(),
								'cmd' => $mode->getId(),
								'type' => 'mode',
							);
						}
					}
				}
			}
		}
		if (is_object($thermostat)) {
			$events = calendar_event::searchByCmd($thermostat->getId());
			if (is_array($events) && count($events) > 0) {
				foreach ($events as $event) {
					$calendar = $event->getEqLogic();
					$stateCalendar = $calendar->getCmd(null, 'state');
					if ($calendar->getIsEnable() == 0 || (is_object($stateCalendar) && $stateCalendar->execCmd() != 1)) {
						continue;
					}
					foreach ($event->getCmd_param('start') as $action) {
						if ($action['cmd'] == '#' . $mode->getId() . '#') {
							$position = 'start';
							$options = $action['options'];
						}
					}
					foreach ($event->getCmd_param('end') as $action) {
						if ($action['cmd'] == '#' . $mode->getId() . '#') {
							if ($position == 'start') {
								$position = null;
							} else {
								$position = 'end';
								$options = $action['options'];
							}
						}
					}
					$nextOccurence = $event->nextOccurrence($position, true);
					if ($nextOccurence['date'] != '' && ($next == null || (strtotime($next['date']) > strtotime($nextOccurence['date']) && strtotime($nextOccurence['date']) > (strtotime('now') + 120)))) {
						$next = array(
							'date' => $nextOccurence,
							'event' => $event,
							'calendar_id' => $calendar->getId(),
							'consigne' => $options['slider'],
							'type' => 'thermostat',
						);
					}
				}
			}
		}
		if ($next == null || $next['date'] == '') {
			return '';
		}

		$cycle = jeedom::evaluateExpression($this->getConfiguration('cycle'));
		if ($next['date'] != '' && strtotime($next['date']) > strtotime(date('Y-m-d H:i:s'))) {
			$temporal_data = $this->calculTemporalData(jeedom::evaluateExpression($next['consigne']), true);
			if ($temporal_data['power'] < 0) {
				log::add('thermostat', 'debug', $this->getHumanName() . ' : Smartstart non pris en compte car power < 0 ' . $temporal_data['power']);
				return;
			}
			$duration = ($temporal_data['power'] * $cycle) / 100;
			if ($duration < 5) {
				log::add('thermostat', 'debug', $this->getHumanName() . ' : Smartstart non pris en compte car la durée ' . $duration);
				return '';
			}
			$next['schedule'] = date('Y-m-d H:i:s', strtotime('-' . round($duration) . ' min ' . $next['date']));
			log::add('thermostat', 'debug', $this->getHumanName() . ' : Smartstart duration : ' . $duration . ' à ' . $next['date'] . ' programmation : ' . $next['schedule']);
			if (strtotime($next['schedule']) > (strtotime('now') + 120)) {
				log::add('thermostat', 'debug', $this->getHumanName() . ' : Next smart schedule date : ' . $next['schedule']);
				$this->reschedule($next['schedule'], false, $next);
			}
		}
	}

	public function preRemove() {
		$cron = cron::byClassAndFunction('thermostat', 'pull', array('thermostat_id' => intval($this->getId())));
		if (is_object($cron)) {
			$cron->remove();
		}
		$cron = cron::byClassAndFunction('thermostat', 'pull', array('thermostat_id' => intval($this->getId()), 'stop' => intval(1)));
		if (is_object($cron)) {
			$cron->remove();
		}
		$listener = listener::byClassAndFunction('thermostat', 'window', array('thermostat_id' => intval($this->getId())));
		if (is_object($listener)) {
			$listener->remove();
		}
		$listener = listener::byClassAndFunction('thermostat', 'hysteresis', array('thermostat_id' => intval($this->getId())));
		if (is_object($listener)) {
			$listener->remove();
		}
	}

	public function preSave() {
		if ($this->getConfiguration('order_max') === '') {
			$this->setConfiguration('order_max', 28);
		}
		if ($this->getConfiguration('order_min') === '') {
			$this->setConfiguration('order_min', 15);
		}
		if ($this->getConfiguration('order_min') > $this->getConfiguration('order_max')) {
			throw new Exception(__('La température de consigne minimum ne peut être supérieur à la consigne maximum', __FILE__));
		}
		if ($this->getConfiguration('coeff_indoor_heat') === '') {
			$this->setConfiguration('coeff_indoor_heat', 10);
		}
		if ($this->getConfiguration('coeff_indoor_cool') === '') {
			$this->setConfiguration('coeff_indoor_cool', 10);
		}
		if ($this->getConfiguration('coeff_outdoor_heat') === '') {
			$this->setConfiguration('coeff_outdoor_heat', 2);
		}
		if ($this->getConfiguration('coeff_outdoor_cool') === '') {
			$this->setConfiguration('coeff_outdoor_cool', 2);
		}
		if ($this->getConfiguration('minCycleDuration') === '') {
			$this->setConfiguration('minCycleDuration', 5);
		}
		if ($this->getConfiguration('offset_heat') === '') {
			$this->setConfiguration('offset_heat', 0);
		}
		if ($this->getConfiguration('offset_cool') === '') {
			$this->setConfiguration('offset_cool', 0);
		}
		if ($this->getConfiguration('minCycleDuration') < 0 || $this->getConfiguration('minCycleDuration') > 90) {
			throw new Exception(__('Le temps de chauffe minimum doit etre compris entre 0% et 90%', __FILE__));
		}
		if ($this->getConfiguration('cycle') === '') {
			$this->setConfiguration('cycle', 60);
		}
		if ($this->getConfiguration('smart_start') === '') {
			$this->setConfiguration('smart_start', 1);
		}
		if ($this->getConfiguration('cycle') < 15) {
			throw new Exception(__('Le temps de cycle doit etre supérieur à 15min', __FILE__));
		}
		if ($this->getConfiguration('autolearn') === '') {
			$this->setConfiguration('autolearn', 1);
		}
		if ($this->getConfiguration('coeff_indoor_cool') === '') {
			$this->setConfiguration('coeff_indoor_cool', 0);
		}
		if ($this->getConfiguration('coeff_indoor_cool_autolearn') === '' || $this->getConfiguration('coeff_indoor_cool_autolearn') < 1) {
			$this->setConfiguration('coeff_indoor_cool_autolearn', 1);
		}
		if ($this->getConfiguration('coeff_indoor_heat_autolearn') === '' || $this->getConfiguration('coeff_indoor_heat_autolearn') < 1) {
			$this->setConfiguration('coeff_indoor_heat_autolearn', 1);
		}
		if ($this->getConfiguration('coeff_outdoor_heat_autolearn') === '' || $this->getConfiguration('coeff_outdoor_heat_autolearn') < 1) {
			$this->setConfiguration('coeff_outdoor_heat_autolearn', 0);
		}
		if ($this->getConfiguration('coeff_outdoor_cool_autolearn') === '' || $this->getConfiguration('coeff_outdoor_cool_autolearn') < 1) {
			$this->setConfiguration('coeff_outdoor_cool_autolearn', 0);
		}
		if (is_array($this->getConfiguration('existingMode'))) {
			foreach ($this->getConfiguration('existingMode') as $existingMode) {
				if (strtolower($existingMode['name']) == __('off', __FILE__)) {
					throw new Exception(__('Vous ne pouvez faire un mode s\'appelant Off car une commande Off est automatiquement creer', __FILE__));
				}
				if (strtolower($existingMode['name']) == __('status', __FILE__)) {
					throw new Exception(__('Vous ne pouvez faire un mode s\'appelant Status car une commande Status éxiste déjà', __FILE__));
				}
				if (strtolower($existingMode['name']) == __('thermostat', __FILE__)) {
					throw new Exception(__('Vous ne pouvez faire un mode s\'appelant Thermostat car une commande Thermostat éxiste déjà', __FILE__));
				}
			}
		}
		$this->setCategory('heating', 1);
	}

	public function postSave() {
		if ($this->getIsEnable() == 1) {
			$order = $this->getCmd(null, 'order');
			if (!is_object($order)) {
				$order = new thermostatCmd();
				$order->setIsVisible(0);
				$order->setUnite('°C');
				$order->setName(__('Consigne', __FILE__));
				$order->setConfiguration('historizeMode', 'none');
				$order->setIsHistorized(1);
			}
			$order->setDisplay('generic_type', 'THERMOSTAT_SETPOINT');
			$order->setEqLogic_id($this->getId());
			$order->setType('info');
			$order->setSubType('numeric');
			$order->setLogicalId('order');
			$order->setConfiguration('maxValue', $this->getConfiguration('order_max'));
			$order->setConfiguration('minValue', $this->getConfiguration('order_min'));
			$order->save();

			$thermostat = $this->getCmd(null, 'thermostat');
			if (!is_object($thermostat)) {
				$thermostat = new thermostatCmd();
				$thermostat->setTemplate('dashboard', 'thermostat');
				$thermostat->setTemplate('mobile', 'thermostat');
				$thermostat->setUnite('°C');
				$thermostat->setName(__('Thermostat', __FILE__));
				$thermostat->setIsVisible(1);
			}
			$thermostat->setDisplay('generic_type', 'THERMOSTAT_SET_SETPOINT');
			$thermostat->setEqLogic_id($this->getId());
			$thermostat->setConfiguration('maxValue', $this->getConfiguration('order_max'));
			$thermostat->setConfiguration('minValue', $this->getConfiguration('order_min'));
			$thermostat->setType('action');
			$thermostat->setSubType('slider');
			$thermostat->setLogicalId('thermostat');
			$thermostat->setValue($order->getId());
			$thermostat->save();

			$status = $this->getCmd(null, 'status');
			if (!is_object($status)) {
				$status = new thermostatCmd();
				$status->setIsVisible(1);
				$status->setName(__('Statut', __FILE__));
			}
			$status->setDisplay('generic_type', 'THERMOSTAT_STATE_NAME');
			$status->setEqLogic_id($this->getId());
			$status->setType('info');
			$status->setSubType('string');
			$status->setLogicalId('status');
			$status->save();

			$actif = $this->getCmd(null, 'actif');
			if (!is_object($actif)) {
				$actif = new thermostatCmd();
				$actif->setName(__('Actif', __FILE__));
				$actif->setIsVisible(0);
				$actif->setIsHistorized(1);
			}
			$actif->setDisplay('generic_type', 'THERMOSTAT_STATE');
			$actif->setEqLogic_id($this->getId());
			$actif->setType('info');
			$actif->setSubType('binary');
			$actif->setLogicalId('actif');
			$actif->save();

			$lockState = $this->getCmd(null, 'lock_state');
			if (!is_object($lockState)) {
				$lockState = new thermostatCmd();
				$lockState->setTemplate('dashboard', 'lock');
				$lockState->setTemplate('mobile', 'lock');
				$lockState->setName(__('Verrouillage', __FILE__));
				$lockState->setIsVisible(0);
			}
			$lockState->setDisplay('generic_type', 'THERMOSTAT_LOCK');
			$lockState->setEqLogic_id($this->getId());
			$lockState->setType('info');
			$lockState->setSubType('binary');
			$lockState->setLogicalId('lock_state');
			$lockState->save();

			$lock = $this->getCmd(null, 'lock');
			if (!is_object($lock)) {
				$lock = new thermostatCmd();
				$lock->setTemplate('dashboard', 'lock');
				$lock->setTemplate('mobile', 'lock');
				$lock->setName('lock');
			}
			$lock->setDisplay('generic_type', 'THERMOSTAT_SET_LOCK');
			$lock->setEqLogic_id($this->getId());
			$lock->setType('action');
			$lock->setSubType('other');
			$lock->setLogicalId('lock');
			if ($this->getConfiguration('hideLockCmd') == 1) {
				$lock->setIsVisible(0);
			} else {
				$lock->setIsVisible(1);
			}
			$lock->setValue($lockState->getId());
			$lock->save();

			$unlock = $this->getCmd(null, 'unlock');
			if (!is_object($unlock)) {
				$unlock = new thermostatCmd();
				$unlock->setTemplate('dashboard', 'lock');
				$unlock->setTemplate('mobile', 'lock');
				$unlock->setName('unlock');
			}
			$unlock->setDisplay('generic_type', 'THERMOSTAT_SET_UNLOCK');
			$unlock->setEqLogic_id($this->getId());
			$unlock->setType('action');
			$unlock->setSubType('other');
			$unlock->setLogicalId('unlock');
			if ($this->getConfiguration('hideLockCmd') == 1) {
				$unlock->setIsVisible(0);
			} else {
				$unlock->setIsVisible(1);
			}
			$unlock->setValue($lockState->getId());
			$unlock->save();

			$temperature = $this->getCmd(null, 'temperature');
			if (!is_object($temperature)) {
				$temperature = new thermostatCmd();
				$temperature->setTemplate('dashboard', 'line');
				$temperature->setTemplate('mobile', 'line');
				$temperature->setName(__('Température', __FILE__));
				$temperature->setIsVisible(1);
				$temperature->setIsHistorized(1);
			}
			$temperature->setEqLogic_id($this->getId());
			$temperature->setType('info');
			$temperature->setSubType('numeric');
			$temperature->setLogicalId('temperature');
			$temperature->setUnite('°C');

			$value = '';
			preg_match_all("/#([0-9]*)#/", $this->getConfiguration('temperature_indoor'), $matches);
			foreach ($matches[1] as $cmd_id) {
				if (is_numeric($cmd_id)) {
					$cmd = cmd::byId($cmd_id);
					if (is_object($cmd) && $cmd->getType() == 'info') {
						$value .= '#' . $cmd_id . '#';
						break;
					}
				}
			}
			$temperature->setValue($value);
			$temperature->setDisplay('generic_type', 'THERMOSTAT_TEMPERATURE');
			$temperature->save();
			$temperature->event($temperature->execute());

			$temperature_outdoor = $this->getCmd(null, 'temperature_outdoor');
			if (!is_object($temperature_outdoor)) {
				$temperature_outdoor = new thermostatCmd();
				$temperature_outdoor->setTemplate('dashboard', 'line');
				$temperature_outdoor->setTemplate('mobile', 'line');
				$temperature_outdoor->setIsVisible(1);
				$temperature_outdoor->setIsHistorized(1);
				$temperature_outdoor->setName(__('Temperature extérieure', __FILE__));
			}
			$temperature_outdoor->setEqLogic_id($this->getId());
			$temperature_outdoor->setType('info');
			$temperature_outdoor->setSubType('numeric');
			$temperature_outdoor->setLogicalId('temperature_outdoor');
			$temperature_outdoor->setUnite('°C');

			$value = '';
			preg_match_all("/#([0-9]*)#/", $this->getConfiguration('temperature_outdoor'), $matches);
			foreach ($matches[1] as $cmd_id) {
				if (is_numeric($cmd_id)) {
					$cmd = cmd::byId($cmd_id);
					if (is_object($cmd) && $cmd->getType() == 'info') {
						$value .= '#' . $cmd_id . '#';
						break;
					}
				}
			}
			$temperature_outdoor->setValue($value);
			$temperature_outdoor->setDisplay('generic_type', 'THERMOSTAT_TEMPERATURE_OUTDOOR');
			$temperature_outdoor->save();

			$offsetheat = $this->getCmd(null, 'offset_heat');
			if (!is_object($offsetheat)) {
				$offsetheat = new thermostatCmd();
				$offsetheat->setName(__('Offset chauffage', __FILE__));
				$offsetheat->setIsVisible(0);
			}
			$offsetheat->setEqLogic_id($this->getId());
			$offsetheat->setType('action');
			$offsetheat->setSubType('slider');
			$offsetheat->setLogicalId('offset_heat');
			$offsetheat->save();

			$offsetcool = $this->getCmd(null, 'offset_cool');
			if (!is_object($offsetcool)) {
				$offsetcool = new thermostatCmd();
				$offsetcool->setName(__('Offset froid', __FILE__));
				$offsetcool->setIsVisible(0);
			}
			$offsetcool->setEqLogic_id($this->getId());
			$offsetcool->setType('action');
			$offsetcool->setSubType('slider');
			$offsetcool->setLogicalId('offset_cool');
			$offsetcool->save();

			$heatOnly = $this->getCmd(null, 'heat_only');
			if (!is_object($heatOnly)) {
				$heatOnly = new thermostatCmd();
				$heatOnly->setName(__('Chauffage seulement', __FILE__));
				$heatOnly->setIsVisible(0);
			}
			$heatOnly->setEqLogic_id($this->getId());
			$heatOnly->setType('action');
			$heatOnly->setSubType('other');
			$heatOnly->setLogicalId('heat_only');
			$heatOnly->save();

			$coolOnly = $this->getCmd(null, 'cool_only');
			if (!is_object($coolOnly)) {
				$coolOnly = new thermostatCmd();
				$coolOnly->setIsVisible(0);
				$coolOnly->setName(__('Climatisation seulement', __FILE__));
			}
			$coolOnly->setEqLogic_id($this->getId());
			$coolOnly->setType('action');
			$coolOnly->setSubType('other');
			$coolOnly->setLogicalId('cool_only');
			$coolOnly->save();

			$allAllow = $this->getCmd(null, 'all_allow');
			if (!is_object($allAllow)) {
				$allAllow = new thermostatCmd();
			}
			$allAllow->setEqLogic_id($this->getId());
			$allAllow->setName(__('Tout autorisé', __FILE__));
			$allAllow->setType('action');
			$allAllow->setSubType('other');
			$allAllow->setLogicalId('all_allow');
			$allAllow->setIsVisible(0);
			$allAllow->save();

			$mode = $this->getCmd(null, 'mode');
			if (!is_object($mode)) {
				$mode = new thermostatCmd();
				$mode->setName(__('Mode', __FILE__));
				$mode->setIsVisible(1);
			}
			$mode->setDisplay('generic_type', 'THERMOSTAT_MODE');
			$mode->setEqLogic_id($this->getId());
			$mode->setType('info');
			$mode->setSubType('string');
			$mode->setLogicalId('mode');
			$mode->save();

			$off = $this->getCmd(null, 'off');
			if (!is_object($off)) {
				$off = new thermostatCmd();
				$off->setIsVisible(1);
				$off->setName(__('Off', __FILE__));
			}
			$off->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');
			$off->setEqLogic_id($this->getId());
			$off->setType('action');
			$off->setSubType('other');
			$off->setLogicalId('off');
			$off->save();
		}
		$knowModes = array();
		if (is_array($this->getConfiguration('existingMode'))) {
			foreach ($this->getConfiguration('existingMode') as $existingMode) {
				$knowModes[$existingMode['name']] = $existingMode;
			}
		}
		foreach ($this->getCmd() as $cmd) {
			if ($cmd->getLogicalId() == 'modeAction') {
				if (isset($knowModes[$cmd->getName()])) {
					$cmd->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');
					if (isset($knowModes[$cmd->getName()]['isVisible'])) {
						$cmd->setIsVisible($knowModes[$cmd->getName()]['isVisible']);
					}
					$cmd->save();
					unset($knowModes[$cmd->getName()]);
				} else {
					$cmd->remove();
				}
			}
		}
		foreach ($knowModes as $knowMode) {
			$mode = new thermostatCmd();
			$mode->setEqLogic_id($this->getId());
			$mode->setName($knowMode['name']);
			$mode->setType('action');
			$mode->setSubType('other');
			$mode->setLogicalId('modeAction');
			if (isset($knowMode['isVisible'])) {
				$mode->setIsVisible($knowMode['isVisible']);
			}
			$mode->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');
			$mode->save();
		}

		if ($this->getIsEnable() == 1) {
			$windows = $this->getConfiguration('window');
			if (count($windows) > 0) {
				$listener = listener::byClassAndFunction('thermostat', 'window', array('thermostat_id' => intval($this->getId())));
				if (!is_object($listener)) {
					$listener = new listener();
				}
				$listener->setClass('thermostat');
				$listener->setFunction('window');
				$listener->setOption(array('thermostat_id' => intval($this->getId())));
				$listener->emptyEvent();
				foreach ($windows as $window) {
					$listener->addEvent($window['cmd']);
				}
				$listener->save();
			}

			if ($this->getConfiguration('engine', 'temporal') == 'hysteresis') {
				$listener = listener::byClassAndFunction('thermostat', 'hysteresis', array('thermostat_id' => intval($this->getId())));
				if (!is_object($listener)) {
					$listener = new listener();
				}
				$listener->setClass('thermostat');
				$listener->setFunction('hysteresis');
				$listener->setOption(array('thermostat_id' => intval($this->getId())));
				$listener->emptyEvent();
				preg_match_all("/#([0-9]*)#/", $this->getConfiguration('temperature_indoor'), $matches);
				foreach ($matches[1] as $cmd_id) {
					$listener->addEvent($cmd_id);
				}
				$listener->save();
				$power = $this->getCmd(null, 'power');
				if (is_object($power)) {
					$power->remove();
				}
			} else {
				$listener = listener::byClassAndFunction('thermostat', 'hysteresis', array('thermostat_id' => intval($this->getId())));
				if (is_object($listener)) {
					$listener->remove();
				}
				$power = $this->getCmd(null, 'power');
				if (!is_object($power)) {
					$power = new thermostatCmd();
					$power->setTemplate('dashboard', 'line');
					$power->setTemplate('mobile', 'line');
					$power->setName(__('Puissance', __FILE__));
					$power->setIsVisible(1);
					$power->setIsHistorized(1);
					$power->setConfiguration('historizeMode', 'none');
				}
				$power->setEqLogic_id($this->getId());
				$power->setType('info');
				$power->setSubType('numeric');
				$power->setLogicalId('power');
				$power->setUnite('%');
				$power->save();
			}
			if ($this->getConfiguration('engine', 'temporal') != 'temporal' || $this->getIsEnable() != 1) {
				$cron = cron::byClassAndFunction('thermostat', 'pull', array('thermostat_id' => intval($this->getId())));
				if (is_object($cron)) {
					$this->stopThermostat();
					$cron->remove();
				}
			}
		} else {
			$cron = cron::byClassAndFunction('thermostat', 'pull', array('thermostat_id' => intval($this->getId())));
			if (is_object($cron)) {
				$cron->remove();
			}
			$cron = cron::byClassAndFunction('thermostat', 'pull', array('thermostat_id' => intval($this->getId()), 'stop' => intval(1)));
			if (is_object($cron)) {
				$cron->remove();
			}
			$listener = listener::byClassAndFunction('thermostat', 'window', array('thermostat_id' => intval($this->getId())));
			if (is_object($listener)) {
				$listener->remove();
			}
			$listener = listener::byClassAndFunction('thermostat', 'hysteresis', array('thermostat_id' => intval($this->getId())));
			if (is_object($listener)) {
				$listener->remove();
			}
		}
	}

	public function heat($_repeat = false) {
		if (!$_repeat) {
			if ($this->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
				return false;
			}
			if ($this->getConfiguration('allow_mode', 'all') != 'all' && $this->getConfiguration('allow_mode', 'all') != 'heat') {
				$this->stopThermostat();
				return false;
			}
			if (count($this->getConfiguration('heating')) == 0) {
				$this->stopThermostat();
				return false;
			}
		}
		log::add('thermostat', 'debug', $this->getHumanName() . ' : Action chauffage');
		$consigne = $this->getCmd(null, 'order')->execCmd();
		foreach ($this->getConfiguration('heating') as $action) {
			try {
				$cmd = cmd::byId(str_replace('#', '', $action['cmd']));
				if (is_object($cmd) && $this->getId() == $cmd->getEqLogic_id()) {
					continue;
				}
				$options = array();
				if (isset($action['options'])) {
					$options = $action['options'];
					foreach ($options as $key => $value) {
						$options[$key] = str_replace('#slider#', $consigne, $value);
					}
				}
				scenarioExpression::createAndExec('action', $action['cmd'], $options);
			} catch (Exception $e) {
				log::add('thermostat', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
			}
		}
		if (!$_repeat) {
			$this->refresh();
			$this->getCmd(null, 'status')->event(__('Chauffage', __FILE__));
			$this->setConfiguration('lastState', 'heat');
			$this->save();
			$this->getCmd(null, 'actif')->event(1);
		}
		return true;
	}

	public function cool($_repeat = false) {
		if (!$_repeat) {
			if ($this->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
				return false;
			}
			if ($this->getConfiguration('allow_mode', 'all') != 'all' && $this->getConfiguration('allow_mode', 'all') != 'cool') {
				$this->stopThermostat();
				return false;
			}
			if (count($this->getConfiguration('cooling')) == 0) {
				$this->stopThermostat();
				return false;
			}
		}
		log::add('thermostat', 'debug', $this->getHumanName() . ' : Action froid');
		$consigne = $this->getCmd(null, 'order')->execCmd();
		foreach ($this->getConfiguration('cooling') as $action) {
			try {
				$cmd = cmd::byId(str_replace('#', '', $action['cmd']));
				if (is_object($cmd) && $this->getId() == $cmd->getEqLogic_id()) {
					continue;
				}
				$options = array();
				if (isset($action['options'])) {
					$options = $action['options'];
					foreach ($options as $key => $value) {
						$options[$key] = str_replace('#slider#', $consigne, $value);
					}
				}
				scenarioExpression::createAndExec('action', $action['cmd'], $options);
			} catch (Exception $e) {
				log::add('thermostat', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
			}
		}
		if (!$_repeat) {
			$this->refresh();
			$this->getCmd(null, 'status')->event(__('Climatisation', __FILE__));
			$this->setConfiguration('lastState', 'cool');
			$this->save();
			$this->getCmd(null, 'actif')->event(1);
		}
		return true;
	}

	public function stopThermostat($_repeat = false) {
		if (!$_repeat && $this->getCmd(null, 'status')->execCmd() == __('Arrêté', __FILE__)) {
			return;
		}
		log::add('thermostat', 'debug', $this->getHumanName() . ' : Action stop');
		$consigne = $this->getCmd(null, 'order')->execCmd();
		foreach ($this->getConfiguration('stoping') as $action) {
			try {
				$cmd = cmd::byId(str_replace('#', '', $action['cmd']));
				if (is_object($cmd) && $this->getId() == $cmd->getEqLogic_id()) {
					continue;
				}
				$options = array();
				if (isset($action['options'])) {
					$options = $action['options'];
					foreach ($options as $key => $value) {
						$options[$key] = str_replace('#slider#', $consigne, $value);
					}
				}
				scenarioExpression::createAndExec('action', $action['cmd'], $options);
			} catch (Exception $e) {
				log::add('thermostat', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
			}
		}
		if ($_repeat) {
			return;
		}
		$this->refresh();
		$this->getCmd(null, 'status')->event(__('Arrêté', __FILE__));
		$this->save();
		$this->getCmd(null, 'actif')->event(0);
		$power = $this->getCmd(null, 'power');
		if (is_object($power)) {
			$power->event(0);
		}
	}

	public function orderChange() {
		if ($this->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
			return;
		}
		if (count($this->getConfiguration('orderChange')) > 0) {
			$consigne = $this->getCmd(null, 'order')->execCmd();
			foreach ($this->getConfiguration('orderChange') as $action) {
				try {
					$cmd = cmd::byId(str_replace('#', '', $action['cmd']));
					if (is_object($cmd) && $this->getId() == $cmd->getEqLogic_id()) {
						continue;
					}
					$options = array();
					if (isset($action['options'])) {
						$options = $action['options'];
						foreach ($options as $key => $value) {
							$options[$key] = str_replace('#slider#', $consigne, $value);
						}
					}
					$options['modeChange'] = true;
					scenarioExpression::createAndExec('action', $action['cmd'], $options);
				} catch (Exception $e) {
					log::add('thermostat', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
				}
			}
		}
	}

	public function failure($_failureRepeat = 999) {
		if ($this->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
			return;
		}
		if (($this->getConfiguration('failureTime', 0) + ($_failureRepeat * 60)) > strtotime('now')) {
			return;
		}
		if (count($this->getConfiguration('failure')) > 0) {
			$consigne = $this->getCmd(null, 'order')->execCmd();
			foreach ($this->getConfiguration('failure') as $action) {
				try {
					$options = array();
					if (isset($action['options'])) {
						$options = $action['options'];
						foreach ($options as $key => $value) {
							$options[$key] = str_replace('#slider#', $consigne, $value);
						}
					}
					scenarioExpression::createAndExec('action', $action['cmd'], $options);
				} catch (Exception $e) {
					log::add('thermostat', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
				}
			}
		}
		$this->setConfiguration('failureTime', strtotime('now'));
		$this->save();
	}

	public function failureActuator() {
		if ($this->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
			return;
		}
		if (count($this->getConfiguration('failureActuator')) > 0) {
			$consigne = $this->getCmd(null, 'order')->execCmd();
			foreach ($this->getConfiguration('failureActuator') as $action) {
				try {
					$options = array();
					if (isset($action['options'])) {
						$options = $action['options'];
						foreach ($options as $key => $value) {
							$options[$key] = str_replace('#slider#', $consigne, $value);
						}
					}
					scenarioExpression::createAndExec('action', $action['cmd'], $options);
				} catch (Exception $e) {
					log::add('thermostat', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
				}
			}
		}
	}

	public function executeMode($_name) {
		$consigne = $this->getCmd(null, 'order')->execCmd();
		foreach ($this->getConfiguration('existingMode') as $existingMode) {
			if ($_name == $existingMode['name']) {
				foreach ($existingMode['actions'] as $action) {
					try {
						$options = array();
						if (isset($action['options'])) {
							$options = $action['options'];
							foreach ($options as $key => $value) {
								$options[$key] = str_replace('#slider#', $consigne, $value);
							}
						}
						scenarioExpression::createAndExec('action', $action['cmd'], $options);
					} catch (Exception $e) {
						log::add('thermostat', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
					}
				}
			}
		}
		$this->getCmd(null, 'mode')->event($_name);
	}

	public function runtimeByDay($_startDate = null, $_endDate = null) {
		$actifCmd = $this->getCmd(null, 'actif');
		if (!is_object($actifCmd)) {
			return array();
		}
		$return = array();
		$prevValue = 0;
		$prevDatetime = 0;
		$day = null;
		$day = strtotime($_startDate . ' 00:00:00 UTC');
		$endDatetime = strtotime($_endDate . ' 00:00:00 UTC');
		while ($day <= $endDatetime) {
			$return[date('Y-m-d', $day)] = array($day * 1000, 0);
			$day = $day + 3600 * 24;
		}
		foreach ($actifCmd->getHistory($_startDate, $_endDate) as $history) {
			if (date('Y-m-d', strtotime($history->getDatetime())) != $day && $prevValue == 1 && $day != null) {
				if (strtotime($day . ' 23:59:59') > $prevDatetime) {
					$return[$day][1] += (strtotime($day . ' 23:59:59') - $prevDatetime) / 60;
				}
				$prevDatetime = strtotime(date('Y-m-d 00:00:00', strtotime($history->getDatetime())));
			}
			$day = date('Y-m-d', strtotime($history->getDatetime()));
			if (!isset($return[$day])) {
				$return[$day] = array(strtotime($day . ' 00:00:00 UTC') * 1000, 0);
			}
			if ($history->getValue() == 1 && $prevValue == 0) {
				$prevDatetime = strtotime($history->getDatetime());
				$prevValue = 1;
			}
			if ($history->getValue() == 0 && $prevValue == 1) {
				if ($prevDatetime > 0 && strtotime($history->getDatetime()) > $prevDatetime) {
					$return[$day][1] += (strtotime($history->getDatetime()) - $prevDatetime) / 60;
				}
				$prevValue = 0;
			}
		}
		return $return;
	}

}

class thermostatCmd extends cmd {
	/*     * *************************Attributs****************************** */

	public function imperihomeGenerate($ISSStructure) {
		$eqLogic = $this->getEqLogic();
		$object = $eqLogic->getObject();
		$type = 'DevThermostat';
		$info_device = array(
			'id' => $this->getId(),
			'name' => $eqLogic->getName(),
			'room' => (is_object($object)) ? $object->getId() : 99999,
			'type' => $type,
			'params' => array(),
		);
		$info_device['params'] = $ISSStructure[$info_device['type']]['params'];
		$info_device['params'][0]['value'] = '#' . $eqLogic->getCmd('info', 'mode')->getId() . '#';
		$info_device['params'][1]['value'] = '#' . $eqLogic->getCmd('info', 'temperature')->getId() . '#';
		$info_device['params'][2]['value'] = '#' . $eqLogic->getCmd('info', 'order')->getId() . '#';
		$info_device['params'][3]['value'] = 0.5;
		$info_device['params'][4]['value'] = 'Off';
		foreach ($eqLogic->getConfiguration('existingMode') as $existingMode) {
			$info_device['params'][4]['value'] .= ',' . $existingMode['name'];
		}
		return $info_device;
	}

	public function imperihomeAction($_action, $_value) {
		$eqLogic = $this->getEqLogic();
		if ($_action == 'setSetPoint') {
			$cmd = $eqLogic->getCmd('action', 'thermostat');
			$cmd->execCmd(array('slider' => $_value));
		}
		if ($_action == 'setMode') {
			if ($_value == 'Off') {
				$action = $eqLogic->getCmd('action', 'off');
				$action->execCmd();
				return;
			}
			foreach ($eqLogic->getCmd('action', 'modeAction', null, true) as $action) {
				if (is_object($action) && $action->getName() == $_value) {
					$action->execCmd();
					break;
				}
			}

		}
	}

	public function imperihomeCmd() {
		if ($this->getLogicalId() == 'order') {
			return true;
		}
		return false;
	}

	public function dontRemoveCmd() {
		return true;
	}

	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		$lockState = $eqLogic->getCmd(null, 'lock_state');
		if ($this->getLogicalId() == 'lock') {
			$lockState->event(1);
		} else if ($this->getLogicalId() == 'unlock') {
			$lockState->event(0);
		} else if ($this->getLogicalId() == 'offset_heat' || $this->getLogicalId() == 'offset_cool') {
			if (is_numeric($_options['slider'])) {
				$eqLogic->setConfiguration($this->getLogicalId(), $_options['slider']);
				$eqLogic->save();
			}
		} else if ($this->getLogicalId() == 'temperature') {
			return round(jeedom::evaluateExpression($eqLogic->getConfiguration('temperature_indoor')), 1);
		} else if ($this->getLogicalId() == 'temperature_outdoor') {
			return round(jeedom::evaluateExpression($eqLogic->getConfiguration('temperature_outdoor')), 1);
		} else if ($this->getLogicalId() == 'cool_only') {
			$eqLogic->setConfiguration('allow_mode', 'cool');
			$eqLogic->save();
		} else if ($this->getLogicalId() == 'heat_only') {
			$eqLogic->setConfiguration('allow_mode', 'heat');
			$eqLogic->save();
		} else if ($this->getLogicalId() == 'all_allow') {
			$eqLogic->setConfiguration('allow_mode', 'all');
			$eqLogic->save();
		}
		if (!is_object($lockState) || $lockState->execCmd() == 1) {
			$eqLogic->refreshWidget();
			return;
		}
		if ($this->getLogicalId() == 'modeAction') {
			$eqLogic->executeMode($this->getName());
		} else if ($this->getLogicalId() == 'off') {
			$eqLogic->getCmd(null, 'mode')->event(__('Off', __FILE__));
			$eqLogic->stopThermostat();
		} else if ($this->getLogicalId() == 'thermostat') {
			if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
				return;
			}
			$eqLogic->getCmd(null, 'order')->event($_options['slider']);
			if (!isset($_options['modeChange'])) {
				$eqLogic->getCmd(null, 'mode')->event(__('Aucun', __FILE__));
			}
			if ($eqLogic->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
				return;
			}
			$eqLogic->orderChange();
			if ($eqLogic->getConfiguration('engine', 'temporal') == 'temporal') {
				thermostat::temporal(array('thermostat_id' => $eqLogic->getId()));
			}
			if ($eqLogic->getConfiguration('engine', 'temporal') == 'hysteresis') {
				thermostat::hysteresis(array('thermostat_id' => $eqLogic->getId()));
			}
		}
	}

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */
}

?>
