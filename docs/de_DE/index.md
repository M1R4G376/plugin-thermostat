# Thermostat-Plugin

Mit diesem Plugin können Sie Thermostate erstellen und verwalten, um die Heizung Ihres Hauses zu steuern. Es arbeitet in 2 Modi Ihrer Wahl :

-   der Modus **Hysterese** entspricht dem Ein- und Ausschalten der Heizung in Abhängigkeit von der Innentemperatur in Bezug auf eine dem Sollwert entsprechende Schwelle. Durch die Hysterese kann ein zu häufiges Umschalten vermieden werden, wenn die Temperatur um den Sollwert liegt..
-   der Modus **temporel** berechnet einen Prozentsatz der Erwärmung über einen vordefinierten Zeitzyklus unter Berücksichtigung der Unterschiede zwischen dem Sollwert und den Innen- und Außentemperaturen (Isolierung). Dieser Modus ist präziser, verfügt über eine Lernfunktion, die eine automatische Anpassung der Koeffizienten ermöglicht, erfordert jedoch möglicherweise einige manuelle Anpassungen, um sie an Ihre Installation anzupassen.. Damit der Zeitmodus funktioniert, benötigen Sie unbedingt einen Innen- UND Außentemperatursensor.

# Configuration

Dieses Plugin dient zum Erstellen von Thermostaten in Jeedom. Es kann Heizung, Klimaanlage oder beides steuern.

Der Vorteil gegenüber einem herkömmlichen Thermostat besteht darin, dass er vollständig in Ihre Hausautomationsinstallation integriert werden kann. Zusätzlich zur Temperaturregelung kann der Thermostat mit allen Geräten im Haus interagieren, da dies zuerst gefragt wird.

Zu seinen Merkmalen gehören :

-   unter Berücksichtigung der Außentemperatur, also des Dämmkoeffizienten des Hauses,
-   ein Regulierungssystem, das lernt, die Regulierung zu optimieren,
-   die Möglichkeit, die Türen zu verwalten, um den Thermostat auszuschalten,
-   Fehlermanagement von Geräten, Temperatursensoren und Heizungen,
-   vollständige Programmierung mit dem Agenda-Plugin, insbesondere mit der Möglichkeit, die Änderung des Sollwerts so zu antizipieren, dass die Temperatur zum geplanten Zeitpunkt erreicht wird (Smart Start)

Zuerst zeigen wir Ihnen die Implementierung, dann detaillieren wir die verschiedenen Einstellungen der Thermostatkonfiguration und schließlich anhand einiger Anwendungsfälle, wie wir sie in Kombination mit anderen Plugins oder erweitern können Szenarien verwenden.

## Konfiguration mit wenigen Klicks

Der Jeedom-Thermostat ist sehr leistungsstark, aber für den traditionellen Gebrauch ist seine Implementierung sehr einfach und schnell, sobald wir die wesentlichen Schritte verstanden haben :

-   Definition des Thermostatmotors (Hysterese oder Zeit). Es ist die Wahl des Regelungsalgorithmus.
-   Konfiguration und Betriebsbereich : Nur Heizung, Klimaanlage oder beides, minimale und maximale Nutzungstemperaturen.
-   Festlegen der Maßnahmen, die der Thermostat zum Heizen, Kühlen oder Abschalten ergreifen soll.

Dann gibt es verschiedene Registerkarten :

-   Die Moduskonfiguration definiert vorgegebene eingestellte Temperaturen. Zum Beispiel Komfortmodus bei 20 ° C, Öko bei 18 ° C.. Es kann auch Tag, Nacht, Urlaub, Abwesenheit geben, ... Sie sehen hier die Möglichkeiten der Anpassung des Plugins.
-   Um die Betriebsart des Thermostats zu verfeinern, können Sie auch Öffnungen konfigurieren, die die Regelung vorübergehend unterbrechen (z. B. kann ein offenes Fenster die Heizung stoppen).. Die Definition dieser Unterbrechung erfolgt hier einfach.
-   Die Verwaltung von Fehlermodi für Temperatursensoren oder zum Heizen ermöglicht es, Aktionen zu definieren, die für einen verschlechterten Modus ausgeführt werden sollen.
-   Auf der Registerkarte Erweiterte Konfiguration können Sie die Heizungsregelungsparameter anpassen.
-   Wenn Sie zusätzlich das Agenda-Plugin haben, können Sie den Programmiermodus direkt über die Registerkarte Programmierung ändern.

Ihr Thermostat ist jetzt betriebsbereit. Durch die Verwendung von Szenarien oder durch die Kombination mit anderen Plugins (Agenda, virtuell, Präsenz, ...) fügt er sich nahtlos in Ihre Hausautomationsinstallation ein. Das bekommen wir auf dem Dashboard :

![Aspect sur le dashboard](../images/thermostat.png)

Mit der Sperre des Widgets können Sie den Thermostat nach einem unvorhergesehenen Ereignis in einem bestimmten Sollwert blockieren. : verlassen, Gäste, ....

## Die Erstellung eines Thermostats im Detail

Um einen neuen Thermostat zu erstellen, rufen Sie die Konfigurationsseite auf, indem Sie im Menü Plugins / Wohlbefinden nach unten scrollen und Thermostat auswählen. Klicken Sie auf die Schaltfläche *Ajouter* befinden Sie sich oben links und geben Sie den gewünschten Namen für Ihren Thermostat ein.

![Konfiguration générale](../images/thermostat_config_générale.png)

Zunächst werden wir die allgemeinen Parameter des Thermostats informieren. Sie befinden sich oben links im allgemeinen Abschnitt. Hier müssen das übergeordnete Objekt, die Aktivierung und die Sichtbarkeit des Thermostats angegeben werden. Dies sind die üblichen Informationen für jeden Benutzer von jeedom.

## Die Wahl des Thermostat-Algorithmus

![Choix de l'algorithme](../images/thermostat31.png)

In diesem Bild ist der Thermostatbetriebsmotor hervorgehoben.. Es gibt 2 mögliche Algorithmen zur Temperaturregelung.

Wenn Sie den Hysteresemodus auswählen, beginnt die Heizung, wenn die Temperatur unter dem Sollwert abzüglich der Hysterese liegt, und schaltet sich aus, sobald die Temperatur den Sollwert plus die Hysterese überschreitet.

![Principe du mode hystérésis](../images/PrincipeHysteresis.png)

Wenn beispielsweise die Hysterese auf 1 ° C und der Sollwert auf 19 ° C eingestellt ist, wird die Heizung aktiviert, wenn die Temperatur unter 18 ° C fällt, und stoppt, sobald sie 20 erreicht ° C..

Die anzugebenden Parameter sind die Hysterese in ° C und der Befehl, mit dem die Temperaturmessung abgerufen werden kann. Die Hysterese wird entsprechend der Genauigkeit des Sensors eingestellt, beispielsweise für eine präzise Sonde bei 0.5 ° C, eine Hysterese von 0.2 ° C ist ein guter Kompromiss.

> **Tip**
>
> Der Hystereseparameter befindet sich auf der Registerkarte *Fortschrittlich*.

Im Zeitmodus wird die Heizungs- oder Klimaanlagensteuerung in einem vordefinierten Zyklus definiert und die Dauer der Ausführung der Steuerung ist eine Funktion der Differenz zwischen dem Sollwert und der vom Sensor gemessenen Temperatur.. Der Algorithmus berechnet auch die Heiz- (oder Kühl-) Zeit über einen Zyklus entsprechend der Trägheit und der Isolierung des Raums.

![Principe du mode zeitlich](../images/PrincipeTemporel.png)

Schließlich ist die Regelung umso langsamer, je länger die Zykluszeit ist. Umgekehrt führt eine zu kurze Zeit zu einem häufigen Umschalten Ihres Heizungssystems, das möglicherweise keine Zeit hat, das Raumvolumen effektiv zu erwärmen. Es wird empfohlen, diese Zykluszeit nicht zu stark zu verkürzen (akzeptable Werte liegen zwischen 30 und 60 Minuten)..

Diese Art der Regelung ist optimierter, verbessert den Komfort und ermöglicht erhebliche Energieeinsparungen..

## Die Konfiguration

Neben dem Thermostatbetriebsmotor können Sie entscheiden, ob der Thermostat für Heizung, Klimaanlage oder beides verwendet wird. Dann geben Sie den Einsatzbereich an : Die minimalen und maximalen Temperaturen definieren die möglichen Sollwerte, auf die im Widget zugegriffen werden kann.

![Konfiguration du fonctionnement](../images/configFonctionnement.png)

Geben Sie als Nächstes die Befehle an, mit denen die Temperatur gemessen und die Heizung oder Klimaanlage gesteuert wird.. Beachten Sie, dass der Zeitmotor die Außentemperatur kennen muss. Wenn Sie keinen Außensensor haben, kann dies über das Wetter-Plugin bereitgestellt werden.

![Sélection des sondes](../images/selectionsondes.png)

> **Tip**
>
> Die Felder "Untere Temperaturgrenze" und "Obere Temperaturgrenze" definieren den Betriebsbereich des Thermostats, außerhalb dessen ein Heizungsfehler ausgelöst wird. Siehe unten den Abschnitt über Standardaktionen.

Die Steuerung des Kühlers oder der Klimaanlage wird auf der Registerkarte beschrieben *Actions*. Hier können wir verschiedene Aktionen definieren, die unserem Thermostat die Möglichkeit geben, verschiedene Geräte zu steuern (z. B. Betrieb nach Zone oder Steuerung eines anderen Thermostats).

![Lager sur les appareils](../images/actionssurappareil.png)

Aktionen sind solche, die das Heizen, Kühlen (Klimatisieren) und Stoppen des Befehls ermöglichen. Bei jeder Sollwertänderung kann eine zusätzliche Aktion ins Auge gefasst werden, sei es im manuellen oder im automatischen Modus.

## Mode : der Ausgangspunkt für die Automatisierung

Die Modi (auf der Registerkarte definiert *Modes*) sind vorgegebene Thermostatsollwerte, die Ihrem Lebensstil entsprechen. Zum Beispiel der Modus **Nuit** oder **Eco** Geben Sie die gewünschte Temperatur an, wenn alle schlafen. Der Modus **Jour** oder **Confort** bestimmt das Verhalten des Thermostats, um eine angenehme Temperatur zu haben, wenn Sie zu Hause anwesend sind. Hier ist nichts eingefroren. Sie können über Szenarien so viele Modi definieren, wie Sie möchten (wir werden später darauf zurückkommen)..

Im Bild unten der Modus **Confort** hat einen Sollwert von 19 ° C und für den Modus **Eco**, Der Thermostat ist auf 17 ° C eingestellt. Der Modus **Vacances** programmiert den Thermostat bei längerer Abwesenheit auf 15 ° C.. Es ist im Dashboard nicht sichtbar, da es sich um ein Szenario handelt, in dem alle Geräte programmiert sind *vacances* und positionieren Sie so den Thermostat in diesem Modus.

![Définition des modes](../images/Definitionmodes.png)

Gehen Sie wie folgt vor, um einen Modus zu definieren :

-   Klicken Sie auf die Schaltfläche *Modus hinzufügen*,
-   Geben Sie diesem Modus einen Namen, zum Beispiel "Eco",
-   Fügen Sie eine Aktion hinzu und wählen Sie den Befehl *Thermostat* Ihrer Thermostatausrüstung,
-   Stellen Sie die gewünschte Temperatur für diesen Modus ein,
-   Aktivieren Sie das Kontrollkästchen **Visible** um diesen Modus im Thermostat-Widget im Dashboard anzuzeigen.

>**IMPORTANT**
>
>Achtung beim Umbenennen eines Modus ist es unbedingt erforderlich, die Szenarien / Geräte zu überprüfen, die den alten Namen verwenden, um sie an den neuen weiterzugeben

## Die Öffnungen : den Thermostat vorübergehend zu unterbrechen

Stellen Sie sich vor, Sie möchten Ihre Heizung oder Klimaanlage vorübergehend abstellen, um beispielsweise den Raum zu belüften, für den der Thermostat aktiv ist. Um die Öffnung des Fensters zu erkennen, verwenden Sie einen Sensor, der sich an der Öffnung Ihres Fensters befindet. Auf diese Weise können Sie diese Unterbrechung ausführen, indem Sie ihn auf der Registerkarte zur Konfiguration der Öffnungen hinzufügen. Hier können zwei zusätzliche Parameter eingestellt werden: Die Öffnungs- und Schließzeiten des Fensters bewirken, dass der Thermostat stoppt und den Betrieb wieder aufnimmt..

![Konfiguration des ouvertures](../images/configouvertures.png)

So konfigurieren Sie den Vorgang beim Öffnen des Fensters :

-   Wählen Sie die Informationen zum Öffnungssensor im Feld "Öffnung" aus
-   Stellen Sie die Zeit vor dem Ausschalten des Thermostats nach dem Öffnen im Feld `Ausschalten ein, wenn mehr als (min) geöffnet ist :``
-   Passen Sie die Zeit nach dem Schließen des Fensters an, um den Thermostat im Feld `Rekindle neu zu starten, wenn er seit (min) geschlossen ist :``
-   Klicken Sie auf die Schaltfläche *Sauvegarder* die Aufnahme von Öffnungen aufzuzeichnen

> **Tip**
>
> Es können mehrere Öffnungen definiert werden. Dies ist erforderlich, wenn der Thermostat einen Bereich steuert, der aus mehreren Räumen besteht.

> **Tip**
>
> Es ist möglich, einen Alarm einzustellen, wenn die Öffnung länger als xx Minuten dauert.

## Vorhersage eines verschlechterten Modus dank Fehlermanagement

Fehler können entweder von den Temperatursensoren oder der Heizungssteuerung stammen. Der Thermostat kann bei längerer Abweichung der Temperatur vom Sollwert einen Fehler erkennen.

### Ausfall der Temperatursonde

Wenn die vom Thermostat verwendeten Sonden keine zurückgeben **changement** Temperatur, zum Beispiel wenn die Batterien abgenutzt sind, löst der Thermostat Fehleraktionen aus. Wenn der Fehler auftritt, ist es möglich, das Gerät in einen vorbestimmten Betriebsmodus zu versetzen, beispielsweise um die Bestellung eines Pilotdrahtstrahlers zu erzwingen. Einfacher gesagt, das Senden einer Nachricht per SMS oder einer Benachrichtigung ermöglicht es, gewarnt zu werden und manuell einzugreifen.

> **Tip**
>
> Der Parameter, mit dem der Thermostat über einen Sondenfehler entscheiden kann, befindet sich auf der Registerkarte *Fortschrittlich*. Dies ist die "maximale Zeit zwischen 2 Temperaturmessungen".

![Défaillance des sondes](../images/defaillancesonde.png)

So definieren Sie eine Fehleraktion :

-   Klicken Sie auf die Registerkarte *Sondenfehler*,
-   Klicken Sie auf die Schaltfläche *Fügen Sie eine Fehleraktion hinzu*
-   Wählen Sie eine Aktion aus und füllen Sie die zugehörigen Felder aus

Sie können mehrere Aktionen eingeben, die nacheinander ausgeführt werden. Bei komplexeren Aktionen rufen Sie ein Szenario auf (geben Sie "Szenario" ohne Akzent in das Aktionsfeld ein und klicken Sie auf eine andere Stelle, um den Namen des Szenarios einzugeben.).

### Ausfall der Heizung / Klimaanlage

Die ordnungsgemäße Funktion der Heizung oder Klimaanlage wird durch eine gute Befolgung der Anweisungen bedingt. Wenn also die Temperatur vom Betriebsbereich des Thermostats abweicht, werden Heizungs- / Klimaanlagenausfallaktionen eingeleitet. Diese Analyse wird über mehrere Zyklen durchgeführt.

> **Tip**
>
> Der Parameter, mit dem der Thermostat über einen Sondenfehler entscheiden kann, befindet sich auf der Registerkarte *Fortschrittlich*. Dies sind die "Hot Failure Margin" für die Heizung und die "Cold Failure Margin" für die Klimatisierung..

In diesem Bild sendet die Fehleraktion den Befehl, über das Pilotkabel in den Kühler-ECO-Modus zu wechseln, und sendet dann eine Nachricht über das Pushbullet-Plugin.

![Défaillance du chauffage](../images/defaillancechauffage.png)

So definieren Sie eine Fehleraktion :

-   Klicken Sie auf die Registerkarte *Ausfall der Heizung / Klimaanlage*,
-   Klicken Sie auf die Schaltfläche *Fügen Sie eine Fehleraktion hinzu*
-   Wählen Sie eine Aktion aus und füllen Sie die zugehörigen Felder aus

Sie können mehrere Aktionen eingeben, die nacheinander ausgeführt werden. Bei komplexeren Aktionen rufen Sie ein Szenario auf (geben Sie "Szenario" ohne Akzent in das Aktionsfeld ein und klicken Sie auf eine andere Stelle, um den Namen des Szenarios einzugeben.).

## Verwalten Sie Sonderfälle mit der erweiterten Thermostatkonfiguration

Diese Registerkarte enthält alle Parameter zum Einstellen des Thermostats im Zeitmodus. In den meisten Fällen ist es nicht erforderlich, diese Werte zu ändern, da das Selbstlernen die Koeffizienten automatisch berechnet. Selbst wenn sich der Thermostat an die meisten Fälle anpassen kann, können Sie die Koeffizienten für eine für Ihre Installation optimierte Konfiguration anpassen..

![Konfiguration Fortschrittlich du
Thermostat](../ images / configurationavancee.png)

Die Koeffizienten sind wie folgt :

-   **Heizkoeffizient / Kühlkoeffizient** : Dies ist der Gewinn des Regulierungssystems . Dieser Wert wird mit der Differenz zwischen dem Sollwert und der gemessenen Innentemperatur multipliziert, um die Heiz- / Abkühlzeit abzuleiten.
-   **Heißes Lernen / Kaltes Lernen** : Dieser Parameter gibt den Lernfortschritt an. Ein Wert von 1 gibt den Beginn des Lernens an, der Algorithmus führt eine grobe Anpassung der Koeffizienten durch. Wenn dieser Parameter zunimmt, wird die Einstellung verfeinert. Ein Wert von 50 gibt das Ende des Lernens an.
-   **Heizungsisolierung / Klimadämmung** : Dieser Koeffizient wird mit der Differenz zwischen dem Sollwert und der gemessenen Außentemperatur multipliziert, um die Heiz- / Klimatisierungszeit abzuleiten. Es stellt den Beitrag der Außentemperatur zur Heiz- / Kühlzeit dar und sein Wert ist normalerweise niedriger als der Heiz- / Kühlkoeffizient bei einem gut isolierten Raum.
-   **Heiße Isolierung lernen / Kaltisolierung lernen** : gleiche Funktion wie oben, jedoch für die Isolationskoeffizienten.
-   **Heizungsversatz (%) / Klimaanlagenversatz (%)** : Der Heizungsoffset ermöglicht es zu berücksichtigen *interne Beiträge*, Normalerweise sollte es nicht festgelegt werden, aber wir nehmen an, dass das Lernen den dynamischen Teil in die anderen 2 Koeffizienten integriert. Die *interne Beiträge*, Es ist zum Beispiel ein Computer, der beim Einschalten einen Temperaturanstieg verursacht, aber es können auch Einzelpersonen (durchschnittlich 1 Person = 80 W) sein, der Kühlschrank in der Küche. In einem Raum im Süden ist es eine sonnige Fassade, die zusätzliche Energie liefern kann. Theoretisch ist dieser Koeffizient negativ.
- **Offset, der angewendet werden soll, wenn der Kühler als heiß eingestuft wird (%)** : zu verwenden, wenn Ihr Heizungssteuerungssystem eine erhebliche Trägheit aufweist, sei es aufgrund der Heizkörper, der Konfiguration des Raums (Abstand zwischen Heizkörper und Temperatursensor) oder des Temperatursensors selbst ( je nach Modell ist ihre Reaktivität mehr oder weniger). Die sichtbare Folge dieser Trägheit ist ein vorübergehendes Überschwingen des Sollwerts bei erheblichen Temperaturerhöhungen (Sollwert beispielsweise von 15 ° C auf 19 ° C).. Dieser Parameter entspricht der Differenz, die zwischen der Heizperiode (= Heizung ist eingeschaltet) und der Periode, in der die von der Sonde gemessene Temperatur ansteigt, geteilt durch die Länge des konfigurierten Zyklus, beobachtet wird.. Wenn beispielsweise zwischen dem Beginn des Erhitzens und dem Beginn des Temperaturanstiegs ein Unterschied von 30 Minuten besteht und die Dauer der Heizzyklen auf 60 Minuten eingestellt ist, können wir diesen Parameter auf 50% einstellen. Wenn also auf einen 100% igen Heizzyklus eine weitere Erwärmung folgt, kann mit diesem Parameter die vom Kühler im ersten Zyklus erzeugte, aber noch nicht von der Sonde gemessene Wärme für die Berechnung des zweiten Zyklus berücksichtigt werden, indem d reduziert wird '' seine Heizleistung. Die Leistung des zweiten Zyklus wird dann gegenüber der Berechnung anhand der von der Sonde gemessenen Temperatur um 50% reduziert..
-   **Selbstlernen** : Kontrollkästchen zum Aktivieren / Deaktivieren des Lernens der Koeffizienten.
-   **Smart start ** : Mit dieser Option können Sie dem Thermostat Informationen geben, indem Sie die Änderung des Sollwerts vorwegnehmen, sodass die Temperatur zur programmierten Zeit erreicht wird. Diese Option erfordert das Agenda-Plugin. Bitte beachten Sie, dass es für einen intelligenten Start wichtig ist, dass das Lernen mehr als 25 beträgt. Ein weiterer Punkt, für den nur das nächste Ereignis erforderlich ist
-   **Zyklus (min)** : Dies ist der Berechnungszyklus des Thermostats. Am Ende des Zyklus und entsprechend der Differenz zwischen den Temperaturen und dem Sollwert berechnet der Thermostat die Heizzeit für den nächsten Zyklus.
-   **Minimale Aufheizzeit (in % des Zyklus)** : Wenn die Berechnung zu einer Heizzeit führt, die unter diesem Wert liegt, ist der Thermostat der Ansicht, dass kein Heizen / Kühlen erforderlich ist, und der Befehl wird auf den nächsten Zyklus übertragen. Dies vermeidet die Beschädigung bestimmter Geräte wie Öfen, erreicht aber auch eine echte Energieeffizienz.
-   **Hot Failure Margin / Cold Failure Margin** : Dieser Wert wird verwendet, um eine Fehlfunktion der Heizung / Klimaanlage zu erkennen. Wenn die Temperatur diesen Bereich im Vergleich zum Sollwert für mehr als 3 aufeinanderfolgende Zyklen überschreitet, schaltet der Thermostat in den Heizungsausfallmodus.
- **Begrenzt unaufhörliche Ein- / Ausschaltzyklen (Pellet, Gas, Heizöl) und PID** : Mit dieser Option können Sie mit verschiedenen Heizstufen regeln. Die Stromrückführung aus dem nächsten Zyklus muss dem Heizgerät den neuen Heizpegelsollwert geben. Die Zyklen enden bei 100%, haben also eine kurze Zykluszeit.

> **Tip**
>
> Lernen ist immer aktiv. Die Initialisierungsphase kann jedoch relativ lang sein (ca. 3 Tage).. Während dieser Phase müssen ausreichend lange Zeiträume vorhanden sein, in denen sich der Sollwert nicht ändert.

## Thermostatsteuerungen

![Liste des commandes dans le résumé domotique](../images/thermostatlistecommandes.png)

Auf alle Befehle kann in der Programmierung nicht zugegriffen werden, einige sind Statusinformationen, die vom Plugin zurückgegeben werden. In den Szenarien finden wir :

![Liste des commandes dans les scénarios](../images/thermostatcommandesscenario.png)

-   **Mode** : Es ist möglich, den Modus zu ändern, indem die Befehle direkt ausgeführt werden (hier Komfort, Komfortmorgen, Öko, Feiertage).
-   **Off** : Dieser Befehl schaltet den Thermostat ab, die Regelung ist nicht mehr aktiv, die Heizung / Klimaanlage ist gestoppt
-   **Thermostat** : Dies ist der Thermostat-Sollwert
-   **lock** : Sperrbefehl, Thermostatstatus kann nicht geändert werden (Moduswechsel, Sollwert)
-   **unlock** : Schaltet den Thermostat frei, um seinen Zustand zu ändern
-   **Nur Heizung** : Regulierung greift nur in die Wärme ein
-   **Nur Klimaanlage** : Die Regelung ist nur zum Kühlen aktiv
-   **Heizungsoffset** : ändert den Versatzkoeffizienten der Heizung entsprechend den internen Beiträgen : Ein Szenario kann diesen Parameter beispielsweise anhand eines Anwesenheitsdetektors ändern
-   **Kaltversatz** : wie oben, aber für die Klimaanlage
-   **Jeder autorisierte** : Ändert das Verhalten des Thermostats, um sowohl beim Heizen als auch beim Klimatisieren zu wirken
-   **Puissance** : Dieser Befehl ist nur im Zeitmodus verfügbar und gibt den Prozentsatz der Heiz- / Kühlzeit über die Zykluszeit an.
-   **Performance** : Nur verfügbar, wenn Sie über eine Außentemperaturregelung und eine Verbrauchsregelung verfügen (in kWh jeden Tag um 00:00 Uhr auf 0 zurückgesetzt).. Dies zeigt Ihnen die Leistung Ihres Heizungssystems im Vergleich zum Tag mit einheitlichem Grad.
-   **Delta-Sollwert** : Mit diesem Befehl, der nur im Zeitmodus verfügbar ist, können Sie ein Berechnungsdelta für den Sollwert eingeben. Wenn> 0, sucht der Thermostat, ob er heizen soll (Sollwert - Delta / 2). Wenn ja, versucht er, sich auf (Sollwert + Delta / 2) zu erwärmen.. Der Vorteil ist, länger, aber seltener zu heizen.

> **Tip**
>
> Die Verwendung des Thermostats im Modus "Nur Heizen" erfordert die Definition der Steuerungen *Zum Heizen muss ich ?* und *Um alles zu stoppen, was ich muss ?* Im Modus "Nur Klimaanlage" sind Steuerungen erforderlich *Um mich abzukühlen, muss ich ?* und *Um alles zu stoppen, was ich muss ?*. Und im Modus "Alle autorisiert" müssen die 3 Befehle eingegeben worden sein.

## Ein konkretes Beispiel für die Verwendung des Thermostats

Wenn Ihr Thermostat konfiguriert ist, müssen Sie die Programmierung durchführen. Die beste Methode, um dies zu erklären, ist ein Anwendungsfall. Deshalb möchten wir unseren Thermostat entsprechend der Anwesenheitszeit der Bewohner des Hauses programmieren.

Zuerst werden wir 2 Szenarien verwenden, um die Heizung in den Modus zu versetzen **Confort** (Sollwert 20 ° C) jeden Morgen der Woche zwischen 5 Uhr morgens und 7:30 Uhr morgens, dann abends zwischen 17 Uhr morgens und 21 Uhr morgens.. Der Modus **Confort** wird auch am Mittwochnachmittag von 12 bis 21 Uhr und am Wochenende von 8 bis 22 Uhr aktiviert.. Den Rest der Zeit schaltet die Heizung auf **Eco**, mit einem Sollwert von 18 ° C..

Also erstellen wir das Szenario ***Komfortheizung***, im programmierten Modus :

![Scénario programmé](../images/thermostat11.png)

und der Code :

![Scenario mode confort](../images/scenarioconfort.png)

Nach dem gleichen Prinzip das Szenario "Öko-Heizung"" :

![Scénario programmé en mode Eco](../images/thermostat13.png)

und sein Code :

![Scénario en mode Eco](../images/scenarioeco.png)

Beachten Sie, dass in den Szenarien die Thermostatsteuerung abgeschlossen ist, da wir auf den Betriebsmodus (nur Heizung oder Klimaanlage), die Modi, den eingestellten Wert und die Sperre (Sperren, Entsperren) reagieren können..

Wenn die Erstellung von Szenarien für die Programmierung eines Thermostats manchmal kompliziert ist, können Sie dies durch die Kombination von Thermostataktionen mit dem Kalender des Agenda-Plugins einfach tun.

Mit dem Agenda-Plugin können Sie weiter programmieren und vor allem das Risiko von Fehlern verringern. In der Tat wird der Kalender im Vergleich zur vorherigen Programmierung klar auf dem Bildschirm angezeigt und wir können Feiertage, Urlaube usw. berücksichtigen..Kurz gesagt, steuern Sie den Thermostat entsprechend seinem Lebensstil.

## Programmieren mit dem Agenda Plugin

Das Agenda-Plugin wird hier nicht vorgestellt. Ziel ist es, es mit der Programmierung des Thermostats zu koppeln. Beachten Sie, dass, wenn Sie das Agenda-Plugin haben, eine Registerkarte *Programmation* wird in der Thermostatkonfiguration angezeigt und ermöglicht den direkten Zugriff auf den zugehörigen Kalender.

Also werden wir eine neue Agenda mit dem Namen erstellen **Heizungsprogrammierung**, Dazu kommen die Änderungsereignisse des Thermostatmodus.
Sobald der Kalender erstellt wurde, fügen wir die Ereignisse Morgen (Montag bis Freitag von 17.00 bis 19.30 Uhr), Abend (Montag, Dienstag, Donnerstag und Freitag von 17.00 bis 21.00 Uhr) und Mittwoch (Mittwoch von 12.00 bis 21.00 Uhr) hinzu. Wochenende (8 bis 22 Uhr), Feiertage. Alle diese Ereignisse haben als Startaktion die Auswahl des Modus **Confort** des Thermostats und als Endaktion den Modus **Eco** :

![Lager de l'agenda](../images/agendaactions.png)

Für die Programmierung der Abendveranstaltung :

![Programmierung de l'évènement](../images/agendaprogrammation.png)

Wiederholen Sie dies einfach für jede Veranstaltung, um diese farbenfrohe monatliche Agenda zu erhalten :

![affichage mensuel de l'agenda](../images/agendamensuel.png)

Zurück zur Thermostatkonfiguration können Sie direkt über die Registerkarte Programmierung auf Kalenderereignisse zugreifen :

![onglund programmation du thermostat](../images/thermostatongletprogrammation.png)

## Visualisierung des Thermostatbetriebs

Sobald der Thermostat konfiguriert ist, ist es wichtig, seine Effizienz zu überprüfen.

![Menu de visualisation des thermostats](../images/menuaccueilthermostats.png)

Im Menü "Home" befindet sich das Untermenü "Thermostat". Das Fenster, das bei Auswahl dieses Menüs angezeigt wird, ist in drei Bereiche unterteilt :

-   Die *widget* Thermostat, um den momentanen Status des Thermostats anzuzeigen,
-   ein Diagramm, das die gesamte Heizzeit pro Tag (in Stunden) darstellt,
-   Ein weiteres Diagramm zeigt die Sollwertkurven, die Innentemperatur und den Heizstatus an.

![cumul du temps de chauffe du thermostat](../images/graphecumultempsdechauffe.png)

*Diagramm der kumulativen Heizzeit*

![graphe des courbes du thermostat](../images/graphecourbesthermostat.png)

*Thermostatkurvendiagramm*

# FAQ

>**Können wir den Thermostat mit einer Fußbodenheizung verwenden, die eine hohe Trägheit hat? ?**
>
>Der Thermostat passt sich praktisch allen Fällen an, dies erfordert jedoch eine eingehende Analyse Ihrer Installation, um die Koeffizienten anzupassen, wenn Sie sich in einer bestimmten Situation befinden.. Siehe den Abschnitt über *Erweiterte Konfiguration* zur Einstellung der Koeffizienten, insbesondere bei Fußbodenheizung. Mehrere Themen im Forum befassen sich mit der Verwendung des Thermostats für verschiedene Heizarten (Herd, Fußbodenheizungskessel usw.)

>**Meine Koeffizienten bleiben in Bewegung**
>
>   Es ist normal, dass das System dank des selbstlernenden Systems seine Koeffizienten ständig korrigiert

>**Wie lange dauert das Lernen im Zeitmodus? ?**
>
>Es dauert durchschnittlich 7 Tage, bis das System optimal gelernt und reguliert hat

>**Ich kann meinen Thermostat nicht programmieren**
>
>Die Programmierung des Thermostats kann entweder in einem Szenario oder unter Verwendung des Agenda-Plugins erfolgen.

>**Mein Thermostat scheint nie in den Heizungs- oder Klimamodus zu wechseln**
>
>Wenn der Thermostat keine Steuerung für Heizung und / oder Klimaanlage hat, kann er nicht in diese Modi wechseln..

>**Egal wie ich die Temperatur oder den Modus ändere, der Thermostat kehrt immer zum vorherigen Zustand zurück**
>
>Stellen Sie sicher, dass Ihr Thermostat nicht verriegelt ist

>**Im Verlaufsmodus ändert mein Thermostat nie den Zustand**
>
>Da die Temperatursonden ihren Wert nicht automatisch erhöhen, ist es ratsam, ein "Cron of Control" einzurichten"

>**Thermostatkurven (insbesondere der Sollwert) scheinen nicht richtig zu sein**
>
>Schauen Sie sich die Glättungsseite des betreffenden Bestellverlaufs an. Um die Effizienz zu steigern, mittelt Jeedom die Werte über 5 Minuten und dann über die Stunde.

>**Die Registerkarte Modus / Aktion ist leer und wenn ich auf die Schaltfläche Hinzufügen klicke, geschieht nichts**
>
>Versuchen Sie, Adblock (oder einen anderen Werbeblocker) zu deaktivieren. Aus unbekannten Gründen blockieren diese das JavaScript der Seite ohne Grund.
