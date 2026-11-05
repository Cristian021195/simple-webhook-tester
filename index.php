<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_auth($config);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
<meta name="robots" content="noindex, nofollow">
<title>Webhook tester</title>
<style>
:root{--bg:#eceff3;--panel:#fff;--ink:#192030;--mute:#5d6778;--line:#d4dae3;--acc:#0b7a75;--acc-ink:#fff;--code:#f5f7fa;--ok:#157f3b;--redir:#2155c4;--warn:#a86400;--bad:#c0262d;
--mono:ui-monospace,"JetBrains Mono","Cascadia Code",Consolas,monospace;--sans:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
@media(prefers-color-scheme:dark){:root{--bg:#0f141b;--panel:#171e29;--ink:#e6eaf0;--mute:#9aa5b6;--line:#2b3544;--acc:#2bb3a8;--acc-ink:#07201e;--code:#0f151d;--ok:#4cc27a;--redir:#6d9cff;--warn:#e0a03a;--bad:#ff6b72}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 var(--sans)}
header{padding:14px 20px;display:flex;gap:12px;align-items:baseline;border-bottom:1px solid var(--line)}
h1{font-size:17px;margin:0}header small{color:var(--mute)}
main{display:grid;gap:16px;padding:16px;max-width:1500px;margin:auto}
@media(min-width:1000px){main{grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:start}}
.card{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px}
.bar{display:flex;gap:8px;flex-wrap:wrap}
input,select,textarea,button{font:inherit;color:inherit;border:1px solid var(--line);background:var(--panel);border-radius:6px;padding:7px 10px}
input,textarea{width:100%}textarea{font-family:var(--mono);font-size:13px;resize:vertical}
input.m,.mono{font-family:var(--mono);font-size:13px}
#url{flex:1;min-width:220px;width:auto}
:focus-visible{outline:2px solid var(--acc);outline-offset:1px}
button{cursor:pointer}button.pri{background:var(--acc);color:var(--acc-ink);border-color:var(--acc);font-weight:600}
button.sm{padding:3px 9px;font-size:13px}button:disabled{opacity:.6;cursor:wait}
.tabs{display:flex;gap:2px;margin:14px 0 10px;border-bottom:1px solid var(--line);overflow-x:auto}
.tabs button{border:0;border-bottom:2px solid transparent;border-radius:0;background:none;color:var(--mute)}
.tabs button[aria-selected=true]{color:var(--ink);border-bottom-color:var(--acc);font-weight:600}
.pane[hidden]{display:none}
label.f{display:block;font-size:13px;color:var(--mute);margin:10px 0 3px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
.row{display:grid;grid-template-columns:auto 1fr 1.4fr auto;gap:6px;margin-bottom:6px;align-items:center}
.chips{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}
.hint{font-size:12.5px;color:var(--mute);margin:6px 0 0}
.chk{display:flex;gap:8px;align-items:center;margin:8px 0}.chk input{width:auto}
pre{margin:0;padding:12px;background:var(--code);border:1px solid var(--line);border-radius:6px;font:13px/1.5 var(--mono);white-space:pre-wrap;word-break:break-all;max-height:460px;overflow:auto}
.status{display:flex;gap:14px;align-items:baseline;flex-wrap:wrap;margin-bottom:6px}
.code{font:700 26px var(--mono)}.s2{color:var(--ok)}.s3{color:var(--redir)}.s4{color:var(--warn)}.s5,.err{color:var(--bad)}
.meta{color:var(--mute);font-size:13px}
table{width:100%;border-collapse:collapse;font:13px var(--mono)}td{padding:4px 8px 4px 0;vertical-align:top;border-bottom:1px solid var(--line);word-break:break-all}td:first-child{color:var(--mute);white-space:nowrap}
.empty{color:var(--mute)}
</style>
</head>
<body>
<header><h1>Webhook tester</h1><small>Dispara peticiones HTTP desde este servidor</small></header>
<main>
<section class="card">
  <form id="f" autocomplete="off">
    <div class="bar">
      <select id="method" aria-label="Método"><?php foreach (['POST','GET','PUT','PATCH','DELETE','HEAD','OPTIONS'] as $m) echo "<option>$m</option>"; ?></select>
      <input id="url" class="m" type="text" placeholder="https://servidor.ejemplo.com/webhooks/pedidos" aria-label="URL">
      <button class="pri" id="send">Enviar</button>
      <button type="button" id="preview">Vista previa</button>
    </div>

    <div class="tabs" role="tablist" id="tabs">
      <button type="button" role="tab" data-p="body" aria-selected="true">Cuerpo</button>
      <button type="button" role="tab" data-p="headers" aria-selected="false">Headers</button>
      <button type="button" role="tab" data-p="auth" aria-selected="false">Autenticación</button>
      <button type="button" role="tab" data-p="sig" aria-selected="false">Firma HMAC</button>
      <button type="button" role="tab" data-p="opts" aria-selected="false">Opciones</button>
    </div>

    <div class="pane" id="p-body">
      <label class="f" for="ctype">Content-Type</label>
      <div class="bar">
        <select id="ctype">
          <option value="application/json">application/json</option>
          <option value="application/x-www-form-urlencoded">application/x-www-form-urlencoded</option>
          <option value="text/plain">text/plain</option>
          <option value="application/xml">application/xml</option>
          <option value="">(sin Content-Type)</option>
          <option value="__custom">Otro…</option>
        </select>
        <input id="ctypeC" class="m" style="flex:1;width:auto" placeholder="application/vnd.api+json" hidden>
      </div>
      <label class="f" for="body">Cuerpo</label>
      <textarea id="body" rows="12" spellcheck="false"></textarea>
      <div class="bar" style="margin-top:8px;align-items:center">
        <button type="button" class="sm" id="fmt">Formatear JSON</button>
        <button type="button" class="sm" id="min">Minificar</button>
        <button type="button" class="sm" id="sample">Ejemplo</button>
        <span class="hint" id="jstate" style="margin:0 0 0 auto"></span>
      </div>
      <p class="hint">Variables: <code>{{uuid}}</code> <code>{{timestamp}}</code> <code>{{iso}}</code> <code>{{random}}</code>. Funcionan en URL, cuerpo y headers.</p>
    </div>

    <div class="pane" id="p-headers" hidden>
      <div class="chips" id="chips"></div>
      <div id="rows"></div>
      <button type="button" class="sm" id="addRow">Añadir header</button>
      <p class="hint">Content-Type, Authorization y la firma se generan desde sus pestañas. Un header escrito aquí con el mismo nombre los reemplaza.</p>
    </div>

    <div class="pane" id="p-auth" hidden>
      <label class="f" for="atype">Tipo</label>
      <select id="atype"><option value="none">Sin autenticación</option><option value="bearer">Bearer token</option><option value="basic">Basic (usuario y contraseña)</option><option value="apikey">API key en header</option></select>
      <div id="a-bearer" hidden><label class="f" for="token">Token</label><input id="token" class="m"></div>
      <div id="a-basic" class="grid" hidden>
        <div><label class="f" for="buser">Usuario</label><input id="buser"></div>
        <div><label class="f" for="bpass">Contraseña</label><input id="bpass" type="password"></div>
      </div>
      <div id="a-apikey" class="grid" hidden>
        <div><label class="f" for="kname">Nombre del header</label><input id="kname" class="m" placeholder="X-API-Key"></div>
        <div><label class="f" for="kval">Valor</label><input id="kval" class="m"></div>
      </div>
    </div>

    <div class="pane" id="p-sig" hidden>
      <label class="chk"><input type="checkbox" id="sEnabled"> Firmar el cuerpo con HMAC</label>
      <label class="f" for="sPreset">Plantilla</label>
      <select id="sPreset">
        <option value="generic">Genérica (X-Signature, hex)</option>
        <option value="github">GitHub (X-Hub-Signature-256)</option>
        <option value="shopify">Shopify (X-Shopify-Hmac-Sha256, base64)</option>
        <option value="ts">Con timestamp (timestamp.cuerpo)</option>
      </select>
      <div class="grid">
        <div><label class="f" for="sHeader">Header de la firma</label><input id="sHeader" class="m"></div>
        <div><label class="f" for="sPrefix">Prefijo del valor</label><input id="sPrefix" class="m" placeholder="sha256="></div>
        <div><label class="f" for="sAlgo">Algoritmo</label><select id="sAlgo"><option>sha256</option><option>sha1</option><option>sha384</option><option>sha512</option></select></div>
        <div><label class="f" for="sEnc">Codificación</label><select id="sEnc"><option value="hex">hex</option><option value="base64">base64</option></select></div>
        <div><label class="f" for="sPayload">Texto firmado</label><select id="sPayload"><option value="body">Solo el cuerpo</option><option value="timestamp_body">timestamp + "." + cuerpo</option></select></div>
        <div><label class="f" for="sTs">Header del timestamp (opcional)</label><input id="sTs" class="m" placeholder="X-Timestamp"></div>
      </div>
      <label class="f" for="sSecret">Secreto compartido</label><input id="sSecret" class="m" type="password">
      <p class="hint">La firma se calcula en el servidor sobre el cuerpo exacto enviado, ya con las variables reemplazadas.</p>
    </div>

    <div class="pane" id="p-opts" hidden>
      <label class="f" for="timeout">Timeout (segundos)</label><input id="timeout" type="number" min="1" max="60" value="15" style="max-width:120px">
      <label class="chk"><input type="checkbox" id="follow"> Seguir redirecciones</label>
      <label class="chk"><input type="checkbox" id="verify" checked> Verificar certificado SSL</label>
      <label class="chk"><input type="checkbox" id="badjson"> Enviar aunque el JSON sea inválido</label>
      <button type="button" class="sm" id="clear">Borrar datos guardados en este navegador</button>
    </div>
  </form>
</section>

<section class="card" id="res" aria-live="polite">
  <p class="empty" id="empty">Aquí aparecerá la respuesta del servidor destino.</p>
  <div id="out" hidden>
    <div class="status"><span class="code" id="rcode"></span><span class="meta" id="rmeta"></span></div>
    <p class="err" id="rerr" hidden></p>
    <div class="tabs" id="rtabs" role="tablist">
      <button type="button" role="tab" data-p="rbody" aria-selected="true">Respuesta</button>
      <button type="button" role="tab" data-p="rheaders" aria-selected="false">Headers</button>
      <button type="button" role="tab" data-p="rreq" aria-selected="false">Petición enviada</button>
    </div>
    <div class="pane" id="p-rbody"><pre id="rbody"></pre></div>
    <div class="pane" id="p-rheaders" hidden><table id="rhead"></table></div>
    <div class="pane" id="p-rreq" hidden><pre id="rreq"></pre></div>
  </div>
</section>
</main>

<script>
(() => {
const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
const CSRF = $('meta[name=csrf]').content, KEY = 'webhook-tester:v1';
const COMMON = [
  ['User-Agent','Webhook-Tester/1.0'],['Accept','application/json'],['X-Request-ID','{{uuid}}'],
  ['X-Delivery-ID','{{uuid}}'],['Idempotency-Key','{{uuid}}'],['X-Webhook-Event','order.created'],
  ['X-Timestamp','{{timestamp}}'],['Cache-Control','no-cache']
];
const PRESETS = {
  generic:{h:'X-Signature',p:'',a:'sha256',e:'hex',pl:'body',t:''},
  github:{h:'X-Hub-Signature-256',p:'sha256=',a:'sha256',e:'hex',pl:'body',t:''},
  shopify:{h:'X-Shopify-Hmac-Sha256',p:'',a:'sha256',e:'base64',pl:'body',t:''},
  ts:{h:'X-Signature',p:'sha256=',a:'sha256',e:'hex',pl:'timestamp_body',t:'X-Timestamp'}
};
const SAMPLE = {id:'{{uuid}}',event:'order.created',created_at:'{{iso}}',data:{order_id:1042,total:59.9,currency:'USD'}};
const F = ['method','url','ctype','ctypeC','body','atype','token','buser','bpass','kname','kval','sPreset','sHeader','sPrefix','sAlgo','sEnc','sPayload','sTs','sSecret','timeout'];
const CK = ['sEnabled','follow','verify','badjson'];

// pestañas
function tabs(id){ $(id).addEventListener('click',e=>{ const b=e.target.closest('button'); if(!b) return;
  $$(id+' button').forEach(x=>x.setAttribute('aria-selected',x===b));
  $$(id==='#tabs'?'.pane[id^=p-]:not([id^=p-r])':'#res .pane').forEach(p=>p.hidden=p.id!=='p-'+b.dataset.p); }); }
tabs('#tabs'); tabs('#rtabs');

// headers
function addRow(n='',v='',on=true){
  const r=document.createElement('div'); r.className='row';
  r.innerHTML='<input type="checkbox" aria-label="Activo"><input class="m" placeholder="Nombre"><input class="m" placeholder="Valor"><button type="button" class="sm" aria-label="Quitar">✕</button>';
  const [c,a,b,x]=r.children; c.checked=on; a.value=n; b.value=v;
  x.onclick=()=>{r.remove();save()}; $('#rows').append(r);
}
COMMON.forEach(([n,v])=>{ const b=document.createElement('button'); b.type='button'; b.className='sm'; b.textContent=n;
  b.onclick=()=>{addRow(n,v);save()}; $('#chips').append(b); });
$('#addRow').onclick=()=>addRow();
const headers=()=>$$('#rows .row').map(r=>({enabled:r.children[0].checked,name:r.children[1].value.trim(),value:r.children[2].value}));

// UI dinámica
const ctype=()=>$('#ctype').value==='__custom'?$('#ctypeC').value.trim():$('#ctype').value;
function sync(){
  $('#ctypeC').hidden=$('#ctype').value!=='__custom';
  ['bearer','basic','apikey'].forEach(t=>$('#a-'+t).hidden=$('#atype').value!==t);
  const js=/json/i.test(ctype()), b=$('#body').value, st=$('#jstate');
  st.className='hint'; st.textContent='';
  if(js&&b.trim()){ try{ JSON.parse(b.replace(/\{\{\w+\}\}/g,'0')); st.textContent='JSON válido'; }catch(e){ st.textContent='JSON inválido: '+e.message; st.className='hint err'; } }
}
$('#sPreset').onchange=()=>{ const p=PRESETS[$('#sPreset').value]; $('#sHeader').value=p.h; $('#sPrefix').value=p.p; $('#sAlgo').value=p.a; $('#sEnc').value=p.e; $('#sPayload').value=p.pl; $('#sTs').value=p.t; save(); };
$('#fmt').onclick=()=>{ try{ $('#body').value=JSON.stringify(JSON.parse($('#body').value),null,2); }catch(e){} sync(); save(); };
$('#min').onclick=()=>{ try{ $('#body').value=JSON.stringify(JSON.parse($('#body').value)); }catch(e){} sync(); save(); };
$('#sample').onclick=()=>{ $('#body').value=JSON.stringify(SAMPLE,null,2); sync(); save(); };

// persistencia local
function state(){ const s={headers:headers()}; F.forEach(k=>s[k]=$('#'+k).value); CK.forEach(k=>s[k]=$('#'+k).checked); return s; }
function save(){ sync(); try{localStorage.setItem(KEY,JSON.stringify(state()))}catch(e){} }
function load(){ let s=null; try{s=JSON.parse(localStorage.getItem(KEY))}catch(e){}
  if(s){ F.forEach(k=>{ if(s[k]!==undefined) $('#'+k).value=s[k]; }); CK.forEach(k=>{ if(s[k]!==undefined) $('#'+k).checked=s[k]; }); (s.headers||[]).forEach(h=>addRow(h.name,h.value,h.enabled)); }
  else { $('#sPreset').onchange(); addRow('X-Request-ID','{{uuid}}'); $('#url').value=''; $('#body').value=JSON.stringify(SAMPLE,null,2); }
  sync(); }
document.addEventListener('input',save); document.addEventListener('change',save);
$('#clear').onclick=()=>{ localStorage.removeItem(KEY); location.reload(); };

// envío
function payload(preview){ const s=state(); return {
  preview, method:s.method, url:s.url.trim(), content_type:ctype(), body:s.body, headers:s.headers.filter(h=>h.name),
  auth:{type:s.atype,token:s.token,user:s.buser,pass:s.bpass,key_name:s.kname,key_value:s.kval},
  signature:{enabled:s.sEnabled,header:s.sHeader,prefix:s.sPrefix,algo:s.sAlgo,encoding:s.sEnc,payload:s.sPayload,timestamp_header:s.sTs,secret:s.sSecret},
  options:{timeout:+s.timeout||15,follow:s.follow,verify_ssl:s.verify,allow_invalid_json:s.badjson}}; }

async function run(preview){
  const btns=$$('#send,#preview'); btns.forEach(b=>b.disabled=true);
  let d;
  try{
    const r=await fetch('ajax/send.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(payload(preview))});
    d=await r.json();
  }catch(e){ d={ok:false,error:'No se pudo contactar con ajax/send.php'}; }
  btns.forEach(b=>b.disabled=false); show(d);
}
function show(d){
  $('#empty').hidden=true; $('#out').hidden=false;
  const err=$('#rerr'); err.hidden=!d.error; err.textContent=d.error||'';
  const rq=d.request, r=d.response, t=d.timing;
  const code=$('#rcode'); code.className='code'; $('#rhead').replaceChildren();
  if(d.preview){ code.textContent='Vista previa'; $('#rmeta').textContent='No se envió nada'; $('#rbody').textContent=''; }
  else if(r){
    code.textContent=r.status||'—'; code.classList.add('s'+String(r.status)[0]);
    $('#rmeta').textContent=[`${t.total} ms (DNS ${t.dns}, conexión ${t.connect}, 1er byte ${t.ttfb})`,r.size+' bytes',r.ip].filter(Boolean).join(' · ');
    let txt=r.body; try{ txt=JSON.stringify(JSON.parse(r.body),null,2); }catch(e){}
    $('#rbody').textContent=(txt||'(cuerpo vacío)')+(r.truncated?'\n\n… respuesta truncada':'');
    r.headers.forEach(([k,v])=>{ const tr=document.createElement('tr'); [k,v].forEach(x=>{const td=document.createElement('td'); td.textContent=x; tr.append(td)}); $('#rhead').append(tr); });
  } else { code.textContent='Error'; $('#rmeta').textContent=''; $('#rbody').textContent=''; }
  $('#rreq').textContent=rq?(rq.raw_headers||`${rq.method} ${rq.url}\n${rq.headers.join('\n')}\n`)+'\n'+rq.body:'';
  if(d.preview||!r) $$('#rtabs button')[2].click();
}
$('#f').addEventListener('submit',e=>{e.preventDefault(); run(false)});
$('#preview').onclick=()=>run(true);
load();
})();
</script>
</body>
</html>
