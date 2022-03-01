<?php
// https://thesecretofthetarot.com/tarot-card-meanings/
define('PLUGIN_TAROT_CARDS',json_decode('[["The Magician","skill, power, action","manipulation, disgrace"],["The High Priestess","wisdom, intuition, mystery","passion, conceit"],["The Empress","initiative, femininity, fruitfulness","dependence, doubt, ignorance"],["The Emperor","power, protection, accomplishment","domination, rigidity"],["The Hierophant","conformity, kindness","servitude, weakness"],["The Lovers","attraction, beauty, love","failure, imbalance"],["The Chariot","triumph, control","trouble, defeat"],["Strength","power, courage","abuse, weakness"],["The Hermit","prudence, introspection","treason, corruption"],["Wheel Of Fortune","success, luck, abundance","bad luck"],["Justice","fairness, justice","unfairness, dishonesty"],["The Hanged Man","sacrifice, suspension","selfishness, indecision"],["Death","transformation, transition","resistance to change"],["Temperance","moderation, balance","excess, competing interests"],["The Devil","materialism, sexuality, vehemence","breaking free, detachment, selfishness, self-destruction"],["The Tower","catastrophe, failure, confusion","danger, fear of change"],["The Star","accomplishments, promises, hope","disappointment, frustration"],["The Moon","danger, enemies, fear","doubt, failure, unhappiness"],["The Sun","success, richness, wealth","lack of success, acceptance, lack of ambition"],["The Last Judgment","judgement, rebirth","conviction, repression"],["The Fool","spontaneity, extravagance, beginnings","carelessness, negligence, vanity"],["The World","completion, accomplishment, fresh new start","lack of direction, lack of closure"],["Page of Wands","enthusiastic, ambitious, curious","superficial, unfaithful"],["Knight of Wands","energetic, powerful, generous","aggressive, offensive"],["Queen of Wands","determined, attractive, kind","unpredictable, vindictive, demanding"],["King of Wands","generous, fair, leader","harsh, impulsive, selfish"],["Ace of Wands","a new personal beginning","bore, delays"],["Two of Wands","planning, future action","lack of action, lack of interest"],["Three of Wands","results of hard work, preparation","work that does not lead anywhere, lack of foresight"],["Four of Wands","harmony, sound base","fear of losing something important, personal safety is questioned"],["Five of Wands","minor fight, mistake","something goes wrong, following an accidental error"],["Six of Wands","personal victory, progress","defeat, lack of confidence"],["Seven of Wands","competition, disputes","giving-up, being overwhelmed"],["Eight of Wands","taking action in order to achieve something important","routine, lack of meaningful action to drive something forward"],["Nine of Wands","something does not work as planned","lack of attention, hesitation"],["Ten of Wands","stress, burden","you get rid of an obstacle"],["Page of Cups","creativity, receiving a message","lack of creativity, lack of emotional intelligence"],["Knight of Cups","charm, romance","mood swings, jealousy"],["Queen of Cups","calm, compassion","dependence, lack of emotional stability"],["King of Cups","generosity, balance","manipulation, moodiness"],["Ace of Cups","love, new relationship","repressed feelings"],["Two of Cups","partnership, taking a love relationship to the next stage","break-up"],["Three of Cups","accomplished family \/ friendship relationship","troubled family \/ friendship relationship"],["Four of Cups","meditation, apathy","missed opportunity"],["Five of Cups","loss, despair","forgiveness, moving on"],["Six of Cups","nostalgia, paying for past mistakes","stuck in the past"],["Seven of Cups","day dreaming, fantasy","illusion, lack of action to make plans come true"],["Eight of Cups","disappointment, withdrawal","walking away"],["Nine of Cups","satisfaction, happiness","dissatisfaction, greed"],["Ten of Cups","happy relationship, harmony","broken relationship"],["Page of Pentacles","financial opportunity, new career","lack of progress"],["Knight of Pentacles","efficiency, conservatism","being \u201cstuck\u201d, boredom, laziness"],["Queen of Pentacles","down-to-earth, motherly","lack of work-life balance"],["King of Pentacles","abundance, security, control","too controlling, authoritative"],["Ace of Pentacles","business opportunity, wealth","financial crisis, sign not to start new ventures"],["Two of Pentacles","balance, prioritization","lack of control in terms of finances"],["Three of Pentacles","collaboration, results of hard work","laziness, lack of teamwork"],["Four of Pentacles","security, conservatism","greed, materialism"],["Five of Pentacles","poverty, insecurity, worries","financial struggle recovery"],["Six of Pentacles","generosity, prosperity","selfishness, debt"],["Seven of Pentacles","reward, perseverance","lack of success"],["Eight of Pentacles","engagement, learning","lack of focus, perfectionism"],["Nine of Pentacles","luxury, gratitude","financial losses, overworking"],["Ten of Pentacles","wealth, retirement","loss, financial failure"],["Page of Swords","determined, smart, curious","sneaky, not serious \/ trustworthy"],["Knight of Swords","rational, hasty, lacking practical sense","break-up, fight"],["Queen of Swords","individualist, thinking on your feet, analytical","cruel, dangerous, bitchy"],["King of Swords","wise, agile, spiritual","cold, shrewd"],["Ace of Swords","a new beginning, power","lack of action at the right time"],["Two of Swords","inability to make-up ones mind","confusion over the next steps"],["Three of Swords","being negatively affected by an action of someone else","recovery after a painful loss"],["Four of Swords","peace, relaxation","lack of focus, nervousness"],["Five of Swords","inconsistent behavior, tension","a coming change, resentments coming from the past"],["Six of Swords","transition, change","stagnation"],["Seven of Swords","betrayal","someone who betrayed you will be exposed"],["Eight of Swords","weakness, imprisonment","pushing through to remove the obstacles"],["Nine of Swords","having a feeling of failure, anxiety","depression"],["Ten of Swords","tragic ending, crisis","survival, recovery"]]'));

$custom_triggers[]=['!tarot', 'function:plugin_tarot', true, '!tarot - performs a tarot reading'];
function plugin_tarot(){
	global $target,$args;
	$args=explode(' ',$args);
	$majs=array_slice(PLUGIN_TAROT_CARDS,0,22);
	$mins=array_slice(PLUGIN_TAROT_CARDS,22,56);
	$out=[];
	$done=[];

	if($args[0]=='major'){
		echo "major reading\n";
		$rands=explode(' ',get_true_random(0,21,15));
		$revs=explode(' ',get_true_random(0,1,4));
		foreach($rands as $rand){
			$m=$majs[$rand];
			foreach($done as $d) if($d[0]==$m[0]) continue(2);
			$done[]=$m;
			$r=array_shift($revs);
			if($r==0) $d=$m[1]; else $d=$m[2];
			$out[]=$m[0].($r==1?' (rev)':'').": $d";
			if(count($done)==4) break;
		}
	} else {
		$arc=get_true_random(0,21);
		$arc=$majs[$arc];
		$rev=explode(' ',get_true_random(0,1,4));
		$r=array_shift($rev);
		if($r==0) $d=$arc[1]; else $d=$arc[2]; // 1 normal 2 reversed
		$out[]=$arc[0].($r==1?' (rev)':'').": $d";
		$mtmp=explode(' ',get_true_random(0,55,15));
		$min=[];
		foreach($mtmp as $m){
			$m=$mins[$m];
			foreach($min as $tmp) if($tmp[0]==$m[0]) continue(2);
			$min[]=$m;
			$r=array_shift($rev);
			if($r==0) $d=$m[1]; else $d=$m[2];
			$out[]=$m[0].($r==1?' (rev)':'').": $d";
			if(count($min)==3) break;
		}
	}
	print_r($out);
	send("PRIVMSG $target :".implode(' * ',$out)."\n");
}
	