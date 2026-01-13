<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>PM5-Converter (PES)</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background:#f5f5f5;
      color:#333;
      margin:0;
      padding:20px;
    }
    input { width:400px; }
    button { padding:6px 12px; margin-left:8px; }

    .player-container {
      display:flex;
      gap:30px;
      flex-wrap:wrap;
      max-width:900px;
      margin:20px auto 0;
    }
    
    .loader-overlay {
  display:none;               /* показываем через JS */
  align-items:center;
  justify-content:center;
  gap:12px;
  margin-top:20px;
  font-size:14px;
  color:#555;
}

.loader-spinner {
  width:22px;
  height:22px;
  border:3px solid rgba(130,130,130,0.3);
  border-top-color:#828282;
  border-radius:50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

    .player-left {
      flex:0 0 300px;
      display:flex;
      flex-direction:column;
      gap:15px;
    }
    .player-right {
      flex:1;
      min-width:300px;
    }
    
    .top-panel{
  text-align:center;
}

.top-controls{
  margin-top:10px;
  display:inline-flex;
  gap:8px;
  align-items:center;
}


    .liquid-glass {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.3);
      border-radius: 12px;
      padding: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .photo-club-box {
      display:flex;
      gap:10px;
      align-items:flex-start;
    }
    .player-photo {
      height:140px;
      width:110px;
      object-fit:cover;
      border-radius:8px;
      background:#ddd;
    }
    .club-logo {
      height:140px;
      width:110px;
      object-fit:contain;
      border-radius:8px;
      background:#eee;
    }

    .player-info h2 {
      font-size:20px;
      margin:0 0 10px 0;
    }
    .player-info p {
      font-size:14px;
      margin:4px 0;
    }

    .abilities-inline-list {
      display:flex;
      flex-wrap:wrap;
      gap:6px;
      list-style:none;
      padding:0;
      margin:0;
    }
    .abilities-inline-list li {
      background:#ffd700;
      padding:4px 8px;
      border-radius:5px;
      font-weight:600;
      color:black;
      display:flex;
      align-items:center;
      font-size:13px;
    }
    .abilities-inline-list li span {
      margin-right:4px;
      display:inline-flex;
      align-items:center;
    }

    .skills-list {
      list-style:none;
      padding:0;
      margin:0;
    }
    .skills-list li {
      display:flex;
      justify-content:space-between;
      padding:8px 12px;
      background:#828282;
      border-radius:6px;
      margin-bottom:6px;
      font-size:14px;
      color:white;
    }
    .skill-label { font-weight:600; }

    .skill-red   { color:red; }
    .skill-orange{ color:orange; }
    .skill-yellow{ color:yellow; }
    .skill-green { color:lightgreen; }
  </style>
</head>
<body>
<div class="top-panel">
  <h2>PM5-Converter (SoFIFA → PES5)</h2>
  <div class="top-controls">
    <input type="text" id="sofifa_url" placeholder="https://sofifa.com/player/220093" />
    <button onclick="convertSofifa()">Сконвертировать</button>
  </div>
</div>

<div id="loader" class="loader-overlay">
  <div class="loader-spinner"></div>
  <span>Загрузка…</span>
</div>
<div id="result"></div>

<script>
function normalizeLatin(str) {
  return str.normalize("NFD").replace(/[\u0300-\u036f]/g, '');
}
function clip99(val) {
  return Math.max(1, Math.min(99, Math.round(val)));
}
function extractShirtNameFromName(name) {
  if (!name || typeof name !== "string") return "—";
  let clean = name.replace(/[.']/g, '').trim();
  let parts = clean.split(/\s+/);
  let last = parts[parts.length - 1];
  last = normalizeLatin(last);
  let letters = [];
  last.split('').forEach(ch => {
    if (ch.match(/\p{L}/u)) {
      letters.push(ch.toUpperCase());
    } else if (ch === '-') {
      letters.push('-');
    }
  });
  return letters.join(' ');
}

function extractSpecialities(html) {
  var doc = document.createElement('div');
  doc.innerHTML = html;
  var spec = [];
  var h5s = doc.querySelectorAll('h5');
  h5s.forEach(function(h5){
    if((h5.textContent||"").trim().toLowerCase()==="player specialities") {
      let col = h5.parentElement;
      let links = col.querySelectorAll('a');
      links.forEach(function(a){
        let txt = a.textContent.replace(/^#/, '').trim();
        if(txt && spec.indexOf(txt)===-1) spec.push(txt);
      });
    }
  });
  return spec;
}

function nationalityToAdjective(country) {
  const dict = {"Italy":"Italian","Spain":"Spanish","Argentina":"Argentinian","France":"French","Germany":"German","England":"English","Brazil":"Brazilian","Portugal":"Portuguese","Belgium":"Belgian","Netherlands":"Dutch","Uruguay":"Uruguayan","Croatia":"Croatian","Russia":"Russian","Serbia":"Serbian","Turkey":"Turkish","Denmark":"Danish","Switzerland":"Swiss","Poland":"Polish"};
  return dict[country]||country;
}
function spacedName(str) {return str.split('').join(' ').toUpperCase();}
function extractName(html) {var doc=document.createElement('div');doc.innerHTML=html;var h1=doc.querySelector('h1.ellipsis');return h1?h1.textContent.trim():"—";}
function extractJsonLd(html){var doc=document.createElement('div');doc.innerHTML=html;var script=doc.querySelector('script[type="application/ld+json"]');if(!script)return{};try{var json=JSON.parse(script.textContent);return json;}catch(e){return {};}}
function extractShirtName(jsonLd){return jsonLd.familyName?spacedName(jsonLd.familyName):"—";}
function extractAgeFromProfile(html){var doc=document.createElement('div');doc.innerHTML=html;var profile=doc.querySelector('div.profile');if(!profile)return"—";var p=profile.querySelector('p');if(!p)return"—";var txt=p.textContent;var match=/(\d+)\s*y\.o\./.exec(txt);return match?match[1]:"—";}
function extractFoot(html){var doc=document.createElement('div');doc.innerHTML=html;var ps=doc.querySelectorAll('p');for(var i=0;i<ps.length;i++){var label=ps[i].querySelector('label');if(label&&label.textContent.trim().toLowerCase()==="preferred foot"){var txt=ps[i].textContent.replace(label.textContent,'').trim();if(txt.toLowerCase().startsWith("left"))return"L";if(txt.toLowerCase().startsWith("right"))return"R";return txt;}}return"—";}
function extractSkills(html) {
  var attributes = [
    "crossing","finishing","heading accuracy","short passing","volleys","dribbling","curve","fk accuracy",
    "long passing","ball control","acceleration","sprint speed","agility","reactions","balance",
    "shot power","jumping","stamina","strength","long shots","aggression","interceptions",
    "attack position","vision","penalties","composure","defensive awareness","standing tackle",
    "sliding tackle","gk diving","gk handling","gk kicking","gk positioning","gk reflexes"
  ];
  var res = {};
  var doc = document.createElement('div');
  doc.innerHTML = html;

  var ps = doc.querySelectorAll('p');
  ps.forEach(function(p) {
    let spans = p.querySelectorAll('span');
    let em = spans.length > 0 ? spans[0].querySelector('em') : null;
    let val = em && em.textContent.match(/^\d+$/) ? parseInt(em.textContent, 10) : null;
    let nameSpan = null;
    for (let s of spans) {
      if (s.hasAttribute('data-tippy-right-start')) {
        nameSpan = s;
        break;
      }
    }
    let name = nameSpan ? nameSpan.textContent.trim().toLowerCase() : '';
    if (val !== null && attributes.includes(name)) {
      res[name] = val;
    }
  });
  attributes.forEach(a => { if (!(a in res)) res[a] = null; });
  return res;
}
function extractStars(html, label){var doc=document.createElement('div');doc.innerHTML=html;var ps=doc.querySelectorAll('p');for(let p of ps){let lbl=p.querySelector('label');if(lbl&&lbl.textContent.trim().toLowerCase()===label.toLowerCase()){let m=p.textContent.match(/(\d+)/);if(m)return parseInt(m[1],10);}}return"—";}
function extractPlaystyles(html){
  var re = /<div[^>]*class=["']col["'][^>]*>\s*<h5>PlayStyles<\/h5>([\s\S]*?)<\/div>/i;
  var m = re.exec(html); var block = m?m[1]:"";
  var res = []; var reStyle = /<span[^>]*data-tippy-right-start[^>]*>([\s\S]*?)<\/span>/gi; var styleMatch;
  while((styleMatch=reStyle.exec(block))!==null){var styleText=styleMatch[1].replace(/<br\s*\/?>/gi,' ').replace(/<[^>]*>/g,'').trim();styleText=styleText.replace(/[^A-Za-z\+\-\s]/g,' ').replace(/\s+/g,' ').trim();if(styleText)res.push(styleText);}
  return res;
}
function extractPositions(html){
  var doc=document.createElement('div');doc.innerHTML=html;var profile=doc.querySelector('div.profile');if(!profile)return[];var allSpans=profile.querySelectorAll('span.pos');var positions=[];allSpans.forEach(function(span){var pos=span.textContent.trim();if(pos&&positions.indexOf(pos)===-1)positions.push(pos);});var bestPosition=null;var labels=doc.querySelectorAll('label');for(var i=0;i<labels.length;i++){if(labels[i].textContent.trim().toLowerCase()==='best position'){var bestSpan=labels[i].nextElementSibling;if(bestSpan&&bestSpan.classList.contains('pos')){bestPosition=bestSpan.textContent.trim();break;}}}var posList=positions.map(function(pos){return(bestPosition&&pos===bestPosition)?(pos+"*"):pos;});return posList;
}
function convertPosition(pos){
  const map={"GK":"GK","CB":"CB","RB":"SB","LB":"SB","CDM":"DM","CM":"CM","RM":"SM","LM":"SM","CAM":"AM","RW":"WF","LW":"WF","ST":"CF"};
  let cleanPos=pos.replace(/\*$/,"");
  let converted=map[cleanPos]||cleanPos;
  return pos.endsWith("*")?(converted+"*"):converted;
}
function dedupePositions(positions){
  let uniquePositions=[];let baseSet=new Set();positions.forEach(function(pos){let base=pos.replace(/\*$/,"");if(!baseSet.has(base)){uniquePositions.push(pos);baseSet.add(base);}else{let idx=uniquePositions.findIndex(p=>p.replace(/\*$/,"")===base);if(pos.endsWith("*")&&idx!==-1&&!uniquePositions[idx].endsWith("*")){uniquePositions[idx]=pos;}}});return uniquePositions;
}
function calcSide(originalPositions){
  const right=["RB","RM","RW"];const left=["LB","LM","LW"];const center=["GK","CB","CDM","CM","CAM","ST"];
  const posSet=new Set(originalPositions.map(pos=>pos.replace(/\*$/,"")));
  const hasRight=right.some(p=>posSet.has(p));const hasLeft=left.some(p=>posSet.has(p));const hasCenter=center.some(p=>posSet.has(p));
  if(!hasRight&&!hasLeft)return"B";if(hasRight&&hasLeft)return"B";if(hasRight&&!hasLeft)return"R";if(hasLeft&&!hasRight)return"L";return"B";
}
function calcInjuryTolerance(playstyles){
  const lowStyles=playstyles.map(s=>s.trim().toLowerCase());
  const hasInjuryProne=lowStyles.some(s=>s.includes("injury prone"));
  const hasSolidPlayer=lowStyles.some(s=>s.includes("solid player"));
  const hasRelentless=lowStyles.some(s=>s.includes("relentless"));
  if(hasInjuryProne)return"C";
  if((hasSolidPlayer||hasRelentless))return"A";
  return"B";
}
function getHeightNum(h){
  if(typeof h=="number")return h;
  if(!h)return 0;
  let m=h.match(/(\d+)/);
  return m?parseInt(m[1],10):0;
}
function getWeightNum(w){
  if(typeof w=="number")return w;
  if(!w)return 0;
  let m=w.match(/(\d+)/);
  return m?parseInt(m[1],10):0;
}
function hasPosShort(posArr, shortArr){
  return posArr.some(pos=>{
    pos=pos.replace(/\*$/,"");
    return shortArr.includes(pos);
  });
}

function extractRoles(html) {
  var doc = document.createElement('div');
  doc.innerHTML = html;
  var roles = [];
  var h5s = doc.querySelectorAll('h5');
  h5s.forEach(function(h5){
    if((h5.textContent||"").trim()==="Roles") {
      let grid = h5.nextElementSibling;
      if(grid && grid.classList.contains('grid') && grid.classList.contains('attribute')) {
        let spans = grid.querySelectorAll('span[data-tippy-right-start]');
        spans.forEach(function(span){
          let parent = span.closest('p');
          if(parent && parent.parentElement && parent.parentElement.classList.contains('col')) {
            let txt = span.innerText.replace(/\s*\+\s*$/,'').trim();
            if(txt && roles.indexOf(txt)===-1) roles.push(txt);
          }
        });
      }
    }
  });
  return roles;
}

function applyPlaystyleBonus(baseValue, playstylesLower, names, plusNames) {
  let value = baseValue;
  playstylesLower.forEach(style => {
    style = style.trim().toLowerCase();
    names.forEach(n => {
      if (style === n) value += 1.5;
    });
  });
  playstylesLower.forEach(style => {
    style = style.trim().toLowerCase();
    plusNames.forEach(n => {
      if (style === n || style.replace(/ \+/g, "+") === n) {
        value += (value >= 90 ? 1.5 : 3);
      }
    });
  });
  return Math.round(value);
}

function hasPosition(posList, checkArr) {
  return posList.some(pos => checkArr.includes(pos.replace(/\*$/,"")));
}
function playstyleMatch(arr, matchArr) {
  return arr.some(s => matchArr.includes(s.replace(/\s*\+$/,"").toLowerCase()));
}
function specialityMatch(arr, matchArr) {
  return arr.some(s => matchArr.includes(s.trim().toLowerCase()));
}

function skillClass(value){
  if (value >= 95) return 'skill-red';
  if (value >= 90) return 'skill-orange';
  if (value >= 80) return 'skill-yellow';
  return 'skill-green';
}

async function convertSofifa() {
  var url = document.getElementById('sofifa_url').value.trim();
  var loaderEl = document.getElementById('loader');
  var resultEl = document.getElementById('result');

  if (!url.match(/^https?:\/\/sofifa\.com\/player\/\d+/)) {
    resultEl.textContent = "Введи корректный URL профиля игрока";
    return;
  }

  // показываем спиннер и очищаем результат
  loaderEl.style.display = 'flex';
  resultEl.innerHTML = '';
  try {
    let resp = await fetch('https://api.allorigins.win/get?url=' + encodeURIComponent(url));
    let data = await resp.json();
    let html = data.contents;
    let specialities = extractSpecialities(html);
    let roles = extractRoles(html);
    let jsonLd = extractJsonLd(html);
    let name = extractName(html);
    let nationality = nationalityToAdjective(jsonLd.nationality || "—");
    let age = extractAgeFromProfile(html);
    let foot = extractFoot(html);
    let skills = extractSkills(html);
    let playstyles = extractPlaystyles(html);
    let positions = extractPositions(html);
    let convertedPositions = dedupePositions(positions.map(convertPosition));
    let side = calcSide(positions.map(pos => pos.replace(/\*$/, "")));
    let injuryTolerance = calcInjuryTolerance(playstyles);

    let skillMoves = extractStars(html, "Skill moves");
    let weakFoot = extractStars(html, "Weak foot");
    let isGK = convertedPositions.some(function(pos) {return pos.replace(/\*$/, "") === "GK";});
    let playstylesLower = playstyles.map(style=>style.toLowerCase());

    let addPositions = [];
    roles.forEach(function(role){
      let rl = role.trim().toLowerCase().replace(/\s*\++\s*$/, "");
      if(rl === "shadow striker" || rl === "false 9") addPositions.push("SS");
      if(rl.includes("wingback")) addPositions.push("WB");
    });
    addPositions.forEach(function(pos){
      if(!convertedPositions.some(p=>p.replace(/\*$/,"")===pos)) convertedPositions.push(pos);
    });

    let str = Number.isFinite(skills["strength"])?skills["strength"]:0;
    let bal = Number.isFinite(skills["balance"])?skills["balance"]:0;
    let wgt = getWeightNum(jsonLd.weight);
    let hgt = getHeightNum(jsonLd.height);
    let st = Number.isFinite(skills["stamina"])?skills["stamina"]:0;
    let ts = Number.isFinite(skills["sprint speed"])?skills["sprint speed"]:0;
    let acc = Number.isFinite(skills["acceleration"])?skills["acceleration"]:0;
    let reac = Number.isFinite(skills["reactions"])?skills["reactions"]:0;
    let inter = Number.isFinite(skills["interceptions"])?skills["interceptions"]:0;
    let gkref = Number.isFinite(skills["gk reflexes"])?skills["gk reflexes"]:0;
    let agi = Number.isFinite(skills["agility"])?skills["agility"]:0;
    let drib = Number.isFinite(skills["dribbling"])?skills["dribbling"]:0;
    let spass = Number.isFinite(skills["short passing"])?skills["short passing"]:0;
    let vis = Number.isFinite(skills["vision"])?skills["vision"]:0;
    let gkk = Number.isFinite(skills["gk kicking"])?skills["gk kicking"]:0;
    let shotpw = Number.isFinite(skills["shot power"])?skills["shot power"]:0;
    let cross = Number.isFinite(skills["crossing"])?skills["crossing"]:0;
    let lpass = Number.isFinite(skills["long passing"])?skills["long passing"]:0;
    let finish = Number.isFinite(skills["finishing"])?skills["finishing"]:0;
    let lshots = Number.isFinite(skills["long shots"])?skills["long shots"]:0;
    let volleys = Number.isFinite(skills["volleys"])?skills["volleys"]:0;
    let fk = Number.isFinite(skills["fk accuracy"])?skills["fk accuracy"]:0;
    let curve = Number.isFinite(skills["curve"])?skills["curve"]:0;
    let headacc = Number.isFinite(skills["heading accuracy"])?skills["heading accuracy"]:0;
    let jump = Number.isFinite(skills["jumping"])?skills["jumping"]:0;
    let gkd = Number.isFinite(skills["gk diving"])?skills["gk diving"]:0;
    let bctrl = Number.isFinite(skills["ball control"])?skills["ball control"]:0;
    let aggr = Number.isFinite(skills["aggression"])?skills["aggression"]:0;
    let attackPosition = Number.isFinite(skills["attack position"])?skills["attack position"]:0;
    let comp = Number.isFinite(skills["composure"])?skills["composure"]:0;
    let gkh = Number.isFinite(skills["gk handling"])?skills["gk handling"]:0;
    let defensiveAwareness = Number.isFinite(skills["defensive awareness"])?skills["defensive awareness"]:0;
    let standingTackle = Number.isFinite(skills["standing tackle"])?skills["standing tackle"]:0;
    let slidingTackle = Number.isFinite(skills["sliding tackle"])?skills["sliding tackle"]:0;
    let penalties = Number.isFinite(skills["penalties"])?skills["penalties"]:0;

    // BALANCE
    let pesBalanceBase = isGK
      ? Math.max(str, (hgt-100+wgt)/2-(100-str)/10)
      : Math.max(str, ([wgt, bal, str].sort((a,b)=>b-a)[0]+[wgt, bal, str].sort((a,b)=>b-a)[1])/2);
    if(isGK) pesBalanceBase = Math.min(pesBalanceBase,90);
    let pesBalance = clip99(applyPlaystyleBonus(
      pesBalanceBase,
      playstylesLower,
      ["press proven","bruiser","aerial fortress","enforcer"],
      ["press proven+","bruiser+","aerial fortress+","enforcer+"]
    ));

    // STAMINA
    let pesStaminaBase = isGK
      ? (st<25?45:st+20)
      : Math.max(st, 50);
    let pesStamina = clip99(applyPlaystyleBonus(
      pesStaminaBase, playstylesLower,
      ["relentless"], ["relentless+"]
    ));

    // TOP SPEED
    let pesTopSpeedBase = isGK
      ? (ts<30?40:ts+10)
      : Math.max(ts,55);
    let pesTopSpeed = clip99(applyPlaystyleBonus(
      pesTopSpeedBase, playstylesLower,
      ["rapid","technical","trickster","jockey","quick step"],
      ["rapid+","technical+","trickster+","jockey+","quick step+"]
    ));

    // ACCELERATION
    let pesAccBase = isGK
      ? (acc<30?40:acc+10)
      : Math.max(acc,50);
    let pesAcc = clip99(applyPlaystyleBonus(
      pesAccBase, playstylesLower,
      ["rapid","technical","trickster","jockey","quick step","footwork"],
      ["rapid+","technical+","trickster+","jockey+","quick step+","footwork+"]
    ));

    // RESPONSE
    let pesRespBase = isGK
      ? gkref+(reac/15)
      : Math.max((reac+inter)/2, reac);
    let pesResp = clip99(applyPlaystyleBonus(
      pesRespBase, playstylesLower,
      ["block","intercept","jockey","anticipate","relentless","quick step","footwork","cross claimer","far reach"],
      ["block+","intercept+","jockey+","anticipate+","relentless+","quick step+","footwork+","cross claimer+","far reach+"]
    ));

    // AGILITY
    let pesAgiBase = isGK
      ? Math.max((gkref+agi)/2,agi,45)
      : Math.max(agi,50);
    let pesAgi = clip99(applyPlaystyleBonus(
      pesAgiBase, playstylesLower,
      ["technical","trickster","footwork"],
      ["technical+","trickster+","footwork+"]
    ));

    // DRIBBLE ACCURACY
    let pesDribAccBase = isGK
      ? (drib<10?40:drib+30)
      : Math.max(drib,60);
    let pesDribAcc = clip99(applyPlaystyleBonus(
      pesDribAccBase, playstylesLower,
      ["first touch","press proven","rapid","trickster","enforcer"],
      ["first touch+","press proven+","rapid+","trickster+","enforcer+"]
    ));

    // DRIBBLE SPEED
    let pesDribSpeedBase;
    if(isGK){
      let arr = [ts, acc, agi].sort((a,b)=>b-a);
      pesDribSpeedBase = (arr[0]+arr[1])/2;
    }else{
      let arr = [drib,ts,acc,agi].sort((a,b)=>b-a);
      pesDribSpeedBase = (arr[0]+arr[1]+arr[2])/3;
    }
    let pesDribSpeed = clip99(applyPlaystyleBonus(
      pesDribSpeedBase, playstylesLower,
      ["first touch","rapid","quick step"],
      ["first touch+","rapid+","quick step+"]
    ));

    // SHORT PASS ACCURACY
    let pesSPA_Base = isGK
      ? spass+15
      : Math.max(Math.max((spass+vis)/2,spass),60);
    let pesSPA = clip99(applyPlaystyleBonus(
      pesSPA_Base, playstylesLower,
      ["dead ball","pinged pass","incisive pass","tiki taka","inventive"],
      ["dead ball+","pinged pass+","incisive pass+","tiki taka+","inventive+"]
    ));

    // SHORT PASS SPEED
    let pesSPS_Base;
    if(isGK){
      pesSPS_Base = 10+(spass+gkk)/2;
    }else{
      let arr = [spass, shotpw, vis].sort((a,b)=>b-a);
      pesSPS_Base = Math.max((arr[0]+arr[1])/2,60);
    }
    let pesSPS = clip99(applyPlaystyleBonus(
      pesSPS_Base, playstylesLower,
      ["dead ball","pinged pass","incisive pass","tiki taka"],
      ["dead ball+","pinged pass+","incisive pass+","tiki taka+"]
    ));

    // LONG PASS ACCURACY
    let pesLPA_Base;
    if(isGK){
      let arr = [lpass, vis, gkk].sort((a,b)=>b-a);
      pesLPA_Base = (arr[0]+arr[1])/2;
    }else{
      let arr = [cross, lpass, vis].sort((a,b)=>b-a);
      pesLPA_Base = Math.max((arr[0]+arr[1])/2,cross,lpass,60);
    }
    let pesLPA = clip99(applyPlaystyleBonus(
      pesLPA_Base, playstylesLower,
      ["dead ball","long ball pass","whipped cross","inventive"],
      ["dead ball+","long ball pass+","whipped cross+","inventive+"]
    ));

    // LONG PASS SPEED
    let pesLPS_Base;
    if(isGK){
      let arr = [lpass, shotpw, vis, gkk].sort((a,b)=>b-a);
      pesLPS_Base = Math.max((arr[0]+arr[1])/2,gkk);
    }else{
      let arr = [cross,lpass,shotpw,vis].sort((a,b)=>b-a);
      pesLPS_Base = Math.max((arr[0]+arr[1])/2,60);
    }
    let pesLPS = clip99(applyPlaystyleBonus(
      pesLPS_Base, playstylesLower,
      ["dead ball","long ball pass","whipped cross"],
      ["dead ball+","long ball pass+","whipped cross+"]
    ));

    // SHOT ACCURACY
    let pesShotAcc_Base = isGK
      ? finish+25
      : Math.max(Math.max((finish+lshots)/2,finish),50);
    let pesShotAcc = clip99(applyPlaystyleBonus(
      pesShotAcc_Base, playstylesLower,
      ["finesse shot","chip shot","inventive","acrobatic","low driven shot"],
      ["finesse shot+","chip shot+","inventive+","acrobatic+","low driven shot+"]
    ));

    // SHOT POWER
    let pesShotPw_Base;
    if(isGK){
      let arr = [shotpw,str,gkk].sort((a,b)=>b-a);
      pesShotPw_Base = Math.max((arr[0]+arr[1])/2,gkk);
    }else{
      let arr = [shotpw,str,lshots].sort((a,b)=>b-a);
      pesShotPw_Base = Math.max((arr[0]+arr[1])/2,shotpw,60);
    }
    let pesShotPw = clip99(applyPlaystyleBonus(
      pesShotPw_Base, playstylesLower,
      ["power shot","dead ball","low driven shot"],
      ["power shot+","dead ball+","low driven shot+"]
    ));

    // SHOT TECHNIQUE
    let pesShotTech_Base = isGK
      ? volleys+25
      : Math.max(Math.max((volleys+lshots)/2,volleys),50);
    let pesShotTech = clip99(applyPlaystyleBonus(
      pesShotTech_Base, playstylesLower,
      ["finesse shot","chip shot","inventive","acrobatic","low driven shot"],
      ["finesse shot+","chip shot+","inventive+","acrobatic+","low driven shot+"]
    ));

    // FREE KICK ACCURACY
    let pesFK_Base = isGK
      ? fk+15
      : Math.max(fk,50);
    let pesFK = clip99(applyPlaystyleBonus(
      pesFK_Base, playstylesLower,
      ["dead ball"],
      ["dead ball+"]
    ));

    // CURLING
    let pesCurling_Base = isGK ? curve+15 : Math.max(curve,50);
    let pesCurling = clip99(applyPlaystyleBonus(
      pesCurling_Base, playstylesLower,
      ["finesse shot","dead ball","incisive pass","whipped cross","gamechanger"],
      ["finesse shot+","dead ball+","incisive pass+","whipped cross+","gamechanger+"]
    ));

    // HEADING
    let pesHeading_Base = isGK ? headacc+30 : Math.max(headacc,50);
    let pesHeading = clip99(applyPlaystyleBonus(
      pesHeading_Base, playstylesLower,
      ["precision header"],
      ["precision header+"]
    ));

    // JUMPING
    let pesJump_Base = isGK ? Math.max(gkd+(jump/10),60) : Math.max(jump,50);
    let pesJump = clip99(applyPlaystyleBonus(
      pesJump_Base, playstylesLower,
      ["aerial fortress","cross claimer","far reach"],
      ["aerial fortress+","cross claimer+","far reach+"]
    ));

    // TECHNIQUE
    let pesTech_Base = isGK?bctrl+25:Math.max(bctrl,50);
    let pesTech = clip99(applyPlaystyleBonus(
      pesTech_Base, playstylesLower,
      ["first touch","inventive","press proven","rapid","technical","acrobatic","gamechanger"],
      ["first touch+","inventive+","press proven+","rapid+","technical+","acrobatic+","gamechanger+"]
    ));

    // AGGRESSION
    let pesAggr_Base = isGK
      ? (reac+vis)/2
      : (aggr>attackPosition ? (aggr+attackPosition)/2 : (reac+attackPosition)/2);
    let pesAggr = clip99(applyPlaystyleBonus(
      pesAggr_Base, playstylesLower,
      ["rapid","relentless","cross claimer","deflector"],
      ["rapid+","relentless+","cross claimer+","deflector+"]
    ));

    // MENTALITY
    let pesMent_Base = isGK
      ? Math.max(comp+15+aggr/10,60)
      : (aggr>comp ? (aggr+comp)/2 : Math.max(comp,50));
    let pesMent = clip99(applyPlaystyleBonus(
      pesMent_Base, playstylesLower,
      ["relentless","team player","leadership","one club player"],
      ["relentless+","team player+","leadership+","one club player+"]
    ));

    // GOALKEEPER SKILLS
    let pesGK_Base = isGK
      ? Math.max(gkh+(comp/20),65)
      : Math.min(skills["gk diving"]+gkh+gkk+skills["gk positioning"]+skills["gk reflexes"],60);
    let pesGK = clip99(applyPlaystyleBonus(
      pesGK_Base, playstylesLower,
      ["footwork","cross claimer","far reach","deflector"],
      ["footwork+","cross claimer+","far reach+","deflector+"]
    ));

    // TEAMWORK
    let pesTeamworkBase = 0;
    if(isGK){
      let arr = [vis,comp,skills["gk positioning"]].sort((a,b)=>b-a);
      pesTeamworkBase = (arr[0]+arr[1])/2;
    }else if(hasPosShort(convertedPositions,["CB","SB","DM"])){
      pesTeamworkBase = (inter+vis+comp+defensiveAwareness)/4;
    }else if(hasPosShort(convertedPositions,["CM","SM","AM","WF","CF"])){
      pesTeamworkBase = Math.max(Math.max((vis+comp)/2,vis),50);
    }
    let pesTeamwork = clip99(applyPlaystyleBonus(
      pesTeamworkBase, playstylesLower,
      ["relentless","team player","leadership","one club player"],
      ["relentless+","team player+","leadership+","one club player+"]
    ));

    // ATTACK
    let pesAttack;
    if (isGK) {
      let arr = [aggr, attackPosition, vis].sort((a,b) => b-a);
      pesAttack = Math.round(((arr[0] + arr[1]) / 2.3));
    } else {
      let val = Math.round((vis + attackPosition) / 2);
      pesAttack = (val < attackPosition) ? attackPosition : val;
    }
    pesAttack = clip99(pesAttack);

    // DEFENCE
    let pesDefence;
    if (isGK) {
      let gkPositioning = Number.isFinite(skills["gk positioning"]) ? skills["gk positioning"] : 0;
      pesDefence = gkPositioning + (reac / 15);
    } else {
      let baseVal = (inter + defensiveAwareness + standingTackle + slidingTackle) / 4;
      pesDefence = (baseVal < defensiveAwareness) ? defensiveAwareness : baseVal;
    }
    let plus15 = ["block","intercept","jockey","slide tackle","anticipate","footwork","cross claimer","far reach"];
    let plus3 = plus15.map(x => x + "+");
    let usePlus = (pesDefence >= 90) ? 1.5 : 3;
    playstylesLower.forEach(function(style) {
      style = style.trim().toLowerCase();
      plus15.forEach(n=>{
        if(style===n) pesDefence += 1.5;
      });
      plus3.forEach(n=>{
        if(style===n || style.replace(/ \+$/,"+")==n) pesDefence += usePlus;
      });
    });
    pesDefence = clip99(pesDefence);

    // CONSISTENCY
    let consistency;
    if (isGK) {
      consistency = Math.floor(1 + (st + comp) / 20);
    } else {
      if (comp >= 1 && comp <= 30) consistency = 1;
      else if (comp <= 45) consistency = 2;
      else if (comp <= 60) consistency = 3;
      else if (comp <= 70) consistency = 4;
      else if (comp <= 80) consistency = 5;
      else if (comp <= 90) consistency = 6;
      else if (comp <= 95) consistency = 7;
      else if (comp <= 99) consistency = 8;
      else consistency = "—";
      if (playstylesLower.includes("relentless") && Number.isInteger(consistency)) consistency += 1;
    }
    if (Number.isInteger(consistency)) {
      if (consistency < 1) consistency = 1;
      if (consistency > 8) consistency = 8;
    }

    // CONDITION/FITNESS
    let condition;
    if (isGK) {
      condition = Math.floor((st + comp) / 15);
    } else {
      let val = (st + comp) / 2;
      if (val >= 1 && val <= 30) condition = 1;
      else if (val <= 45) condition = 2;
      else if (val <= 60) condition = 3;
      else if (val <= 70) condition = 4;
      else if (val <= 80) condition = 5;
      else if (val <= 90) condition = 6;
      else if (val <= 95) condition = 7;
      else if (val <= 99) condition = 8;
      else condition = "—";
      if (Number.isInteger(condition)) {
        if (playstylesLower.includes("relentless") || playstylesLower.includes("solid player")) condition += 1;
        if (playstylesLower.includes("injury prone")) condition -= 1;
        if (condition < 1) condition = 1;
        if (condition > 8) condition = 8;
      }
    }

    // WEAK FOOT ACCURACY
    let weakFootAcc;
    let wf = Number.isFinite(weakFoot) ? weakFoot : 0;
    let bc = Number.isFinite(skills["ball control"]) ? skills["ball control"] : 0;

    if (wf === 1) {
      if (bc >= 1 && bc <= 49) weakFootAcc = 1;
      else if (bc >= 50 && bc <= 99) weakFootAcc = 2;
      else weakFootAcc = "—";
    } else if (wf === 2) {
      if (bc >= 1 && bc <= 49) weakFootAcc = 3;
      else if (bc >= 50 && bc <= 98) weakFootAcc = 4;
      else weakFootAcc = "—";
    } else if (wf === 3) {
      if (bc >= 1 && bc <= 49) weakFootAcc = 4;
      else if (bc >= 50 && bc <= 99) weakFootAcc = 5;
      else weakFootAcc = "—";
    } else if (wf === 4) {
      if (bc >= 1 && bc <= 49) weakFootAcc = 6;
      else if (bc >= 50 && bc <= 99) weakFootAcc = 7;
      else weakFootAcc = "—";
    } else if (wf === 5) {
      if (bc >= 1 && bc <= 49) weakFootAcc = 7;
      else if (bc >= 50 && bc <= 99) weakFootAcc = 8;
      else weakFootAcc = "—";
    } else {
      weakFootAcc = "—";
    }
    if (Number.isInteger(weakFootAcc)) {
      if (weakFootAcc < 1) weakFootAcc = 1;
      if (weakFootAcc > 8) weakFootAcc = 8;
    }

    let playstylesNoPlus = playstyles.map(s=>s.replace(/\s*\+$/,"").toLowerCase());
    let specialitiesLC = specialities.map(s=>s.trim().toLowerCase());
    let posArr = convertedPositions.map(p=>p.replace(/\*$/,""));

    let specialAbilities = [];

    if (
      playstyleMatch(playstylesNoPlus, ["rapid","trickster"])
      || specialityMatch(specialitiesLC, ["dribbler"])
    ) specialAbilities.push("Dribbling");

    if (
      playstyleMatch(playstylesNoPlus, ["technical","trickster"])
      || specialityMatch(specialitiesLC, ["dribbler"])
    ) specialAbilities.push("Tactical dribble");

    if (
      attackPosition>84
      || playstyleMatch(playstylesNoPlus, ["precision header"])
      || (playstyleMatch(playstylesNoPlus, ["aerial"]) && attackPosition>75)
    ) {
      if (hasPosition(posArr, ["CM","AM","CF"])) specialAbilities.push("Positioning");
    }

    if (
      reac>84
      || playstyleMatch(playstylesNoPlus, ["quick step"])
    ) {
      if (hasPosition(posArr, ["SB","CM","SM","AM","WF","CF"])) specialAbilities.push("Reaction");
    }

    if (
      (playstyleMatch(playstylesNoPlus, ["leadership"]) && hasPosition(posArr, ["DM","CM","AM"]))
      || specialityMatch(specialitiesLC, ["playmaker"])
    ) specialAbilities.push("Playmaking");

    if (
      playstyleMatch(playstylesNoPlus, ["pinged pass","incisive pass","long ball pass"])
      || specialityMatch(specialitiesLC, ["playmaker"])
    ) specialAbilities.push("Passing");

    if (
      ((finish+attackPosition)/2 > 84)
      || specialityMatch(specialitiesLC, ["clinical finisher"])
    ) {
      if (hasPosition(posArr, ["CM","SM","AM","WF","CF"])) specialAbilities.push("Scoring");
    }

    if (
      ((finish+comp)/2>84)
      || (playstyleMatch(playstylesNoPlus, ["chip shot"]) && ((finish+comp)/2>78))
      || specialityMatch(specialitiesLC, ["clinical finisher"])
    ) {
      if (hasPosition(posArr, ["CM","SM","AM","WF","CF"])) specialAbilities.push("1-on-1 Scoring");
    }

    if (
      specialityMatch(specialitiesLC, ["aerial threat","strength"])
    ) {
      if (hasPosition(posArr, ["CF"])) specialAbilities.push("Post player");
    }

    if (
      ((attackPosition+comp)/2>81)
      && hasPosition(posArr, ["SB","SM","AM","WF","CF"])
    ) specialAbilities.push("Lines");

    if (
      lshots>84
      || playstyleMatch(playstylesNoPlus, ["power shot"])
      || specialityMatch(specialitiesLC, ["distance shooter"])
    ) specialAbilities.push("Middle shooting");

    let hasSide = (
      (playstyleMatch(playstylesNoPlus, ["whipped pass"]) && hasPosition(posArr, ["SB","SM","WF"]))
      || (specialityMatch(specialitiesLC, ["speedster"]) && hasPosition(posArr, ["SB","SM","AM","WF","CF"]))
      || (specialityMatch(specialitiesLC, ["crosser"]) && hasPosition(posArr, ["SB","SM","WF"]))
    );
    let hasCentre = (
      (lshots>81)
      || playstyleMatch(playstylesNoPlus, ["bruiser","relentless"])
      || specialityMatch(specialitiesLC, ["playmaker"])
    ) && hasPosition(posArr, ["DM","CM","AM","CF"]);
    if (hasCentre) {
      specialAbilities.push("Centre");
    } else if (hasSide) {
      specialAbilities.push("Side");
    }

    if (penalties>84) specialAbilities.push("Penalties");

    if (playstyleMatch(playstylesNoPlus, ["tiki taka","inventive","first touch"])) specialAbilities.push("1-Touch Pass");

    if (playstyleMatch(playstylesNoPlus, ["gamechanger","inventive"])) specialAbilities.push("Outside");

    if (
      defensiveAwareness>84
      || playstyleMatch(playstylesNoPlus, ["jockey"])
    ) specialAbilities.push("Marking");

    if (
      slidingTackle>81
      || playstyleMatch(playstylesNoPlus, ["slide tackle"])
      || specialityMatch(specialitiesLC, ["tackling"])
    ) specialAbilities.push("Sliding");

    if (
      ((inter+defensiveAwareness)/2>81)
      || playstyleMatch(playstylesNoPlus, ["intercept"])
      || specialityMatch(specialitiesLC, ["tackling"])
    ) specialAbilities.push("Covering");

    if (
      ((inter+defensiveAwareness)/2>81)
      || playstyleMatch(playstylesNoPlus, ["leadership"])
      || specialityMatch(specialitiesLC, ["tactician"])
    ) {
      if (hasPosition(posArr, ["CB"])) specialAbilities.push("D-Line control");
    }

    if (playstyleMatch(playstylesNoPlus, ["footwork"]) && hasPosition(posArr, ["GK"])) specialAbilities.push("1-on-1 stopper");

    if (playstyleMatch(playstylesNoPlus, ["far reach"]) && hasPosition(posArr, ["GK"])) specialAbilities.push("Penalty stopper");

    if (playstyleMatch(playstylesNoPlus, ["long throw","far throw"])) specialAbilities.push("Long Throw");

    // WEAK FOOT FREQUENCY
    let weakFootFreq;
    let wfacc = Number.isFinite(weakFootAcc) ? weakFootAcc : null;
    let sm = Number.isFinite(skillMoves) ? skillMoves : null;

    weakFootFreq = (wfacc !== null) ? wfacc : "—";

    if ((wf === 3 || wf === 4 || wf === 5) && Number.isInteger(weakFootFreq)) {
      if (sm === 1 || sm === 2) weakFootFreq -= 1;
      else if (sm === 3) weakFootFreq = weakFootFreq;
      else if (sm === 4) weakFootFreq += 1;
      else if (sm === 5) weakFootFreq += 2;
    }

    let hasGamechanger = playstylesLower.some(
      s => s.replace(' +', '+') === "gamechanger" || s.replace(' +', '+') === "gamechanger+"
    );
    if ((wf === 1 || wf === 2) && hasGamechanger && Number.isInteger(weakFootFreq)) {
      weakFootFreq -= 1;
    }

    let hasTrivela = playstylesLower.some(
      s => s.replace(' +', '+') === "trivela" || s.replace(' +', '+') === "trivela+"
    );
    if (hasTrivela && Number.isInteger(weakFootFreq)) {
      weakFootFreq -= 1;
    }

    if (Number.isInteger(weakFootFreq)) {
      if (weakFootFreq < 1) weakFootFreq = 1;
      if (weakFootFreq > 8) weakFootFreq = 8;
    }

    let abilitiesMap = {
      "Dribbling": "Dribbling",
      "Tactical dribble": "Tactical Dribble",
      "Positioning": "Positioning",
      "Reaction": "Reaction",
      "Playmaking": "Playmaking",
      "Passing": "Passing",
      "Scoring": "Scoring",
      "1-on-1 Scoring": "1-on-1 Scoring",
      "Post player": "Post Player",
      "Lines": "Lines",
      "Middle shooting": "Middle Shooting",
      "Side": "Side",
      "Centre": "Centre",
      "Penalties": "Penalties",
      "1-Touch Pass": "1-Touch Pass",
      "Outside": "Outside",
      "Marking": "Marking",
      "Sliding": "Sliding",
      "Covering": "Covering",
      "D-Line control": "D-Line Control",
      "Penalty stopper": "Penalty Stopper",
      "1-on-1 stopper": "1-on-1 Stopper",
      "Long Throw": "Long Throw"
    };

    let skillsMap = [
      ['Attack', pesAttack],
      ['Defence', pesDefence],
      ['Balance', pesBalance],
      ['Stamina', pesStamina],
      ['Speed', pesTopSpeed],
      ['Acceleration', pesAcc],
      ['Response', pesResp],
      ['Agility', pesAgi],
      ['Dribble Accuracy', pesDribAcc],
      ['Dribble Speed', pesDribSpeed],
      ['Short Pass Accuracy', pesSPA],
      ['Short Pass Speed', pesSPS],
      ['Long Pass Accuracy', pesLPA],
      ['Long Pass Speed', pesLPS],
      ['Shot Accuracy', pesShotAcc],
      ['Shot Power', pesShotPw],
      ['Shot Technique', pesShotTech],
      ['Free Kick Accuracy', pesFK],
      ['Curling', pesCurling],
      ['Header', pesHeading],
      ['Jump', pesJump],
      ['Technique', pesTech],
      ['Aggression', pesAggr],
      ['Mentality', pesMent],
      ['GK Ability', pesGK],
      ['Teamwork', pesTeamwork]
    ];

    let abilitiesHtml = specialAbilities.map(ab => {
      let label = abilitiesMap[ab] || ab;
      return `<li><span>★</span> ${label}</li>`;
    }).join('');

    let skillsHtml = skillsMap.map(([label, val]) => {
      if (val === null || val === undefined || val === '—') return '';
      let cls = skillClass(val);
      return `
        <li>
          <span class="skill-label">${label}</span>
          <span class="${cls}">${val}</span>
        </li>`;
    }).join('');

    let positionsHtml = convertedPositions.length ? convertedPositions.join(', ') : '—';

    // заглушки для фото и эмблемы
    let playerPhotoSrc = 'img/players/default.png';
    let clubLogoSrc =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" width="220" height="280" viewBox="0 0 220 280">
  <defs>
    <linearGradient id="shieldGrad" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0%" stop-color="#3b4b66" stop-opacity="0.95"/>
      <stop offset="100%" stop-color="#111827" stop-opacity="0.95"/>
    </linearGradient>
  </defs>
  <!-- Щит с лёгкой прозрачностью, фон страницы будет просвечивать -->
  <path d="M110 10 L200 50 L190 170 C185 210 155 245 110 265 C65 245 35 210 30 170 L20 50 Z"
        fill="url(#shieldGrad)"
        stroke="#ffffff"
        stroke-width="4"
        stroke-linejoin="round"
        fill-opacity="0.85"/>
  <!-- Вопросительный знак -->
  <text x="110" y="145"
        text-anchor="middle"
        dominant-baseline="middle"
        font-family="Arial, sans-serif"
        font-size="120"
        fill="#ffffff">?</text>
</svg>
`);

    document.getElementById('result').innerHTML = `
<div class="player-container">
  <div class="player-left">
    <div class="liquid-glass">
      <div class="photo-club-box">
        <img src="${playerPhotoSrc}" alt="Фото игрока" class="player-photo">
        <img src="${clubLogoSrc}" alt="Логотип клуба" class="club-logo">
      </div>
    </div>

    <div class="player-info liquid-glass">
      <h2>${name}</h2>
      <p><strong>Национальность:</strong> ${nationality}</p>
      <p><strong>Возраст:</strong> ${age}</p>
      <p><strong>Рост / Вес:</strong> ${jsonLd.height || '—'} / ${jsonLd.weight || '—'}</p>
      <p><strong>Рабочая нога:</strong> ${foot}</p>
      <p><strong>Травма:</strong> ${injuryTolerance}</p>
      <p><strong>Позиции:</strong> ${positionsHtml}</p>
    </div>

    <div class="liquid-glass">
      <ul class="abilities-inline-list">
        ${abilitiesHtml || ''}
      </ul>
    </div>
  </div>

  <div class="player-right liquid-glass">
    <ul class="skills-list">
      ${skillsHtml}
    </ul>
  </div>
</div>`;
    loaderEl.style.display = 'none';
  } catch(e) {
  document.getElementById('result').textContent = "Ошибка загрузки/парсинга!\n" + e;
  document.getElementById('loader').style.display = 'none';
}
}
</script>
</body>
</html>
