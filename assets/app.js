document.addEventListener('DOMContentLoaded',()=>{
 const form=document.querySelector('#general-form'), area=document.querySelector('#specific-area'); if(!form||!area)return;
 form.addEventListener('submit',async ev=>{ev.preventDefault();const ids=[...form.querySelectorAll('input:checked')].map(x=>Number(x.value));if(!ids.length){alert('Pilih setidaknya satu gejala umum.');return;}const res=await fetch((window.KOPICARE_BASE||'')+'/api/diagnose.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({stage:'specific',symptoms:ids})});const data=await res.json();if(data.error){alert(data.error);return;}if(!data.diseases.length){area.innerHTML='<div class="alert">Belum ada penyakit yang terhubung ke gejala pilihan. Silakan hubungi admin.</div>';return;}window.generalIds=ids;area.innerHTML='<section class="page-head"><span class="eyebrow">LANGKAH 2 DARI 2</span><h1>Pertanyaan lanjutan</h1><p>Untuk setiap kemungkinan penyakit, nilai seberapa yakin Anda melihat tiap gejala.</p></section>'+data.diseases.map((d,i)=>`<section class="card disease-questions"><span class="eyebrow">KEMUNGKINAN ${i+1}</span><h2>${esc(d.name)}</h2><p>${esc(d.description||'Periksa gejala yang tampak pada tanaman.')}</p>${d.symptoms.length?d.symptoms.map(s=>`<label class="question"><span><b>${esc(s.name)}</b><small>${esc(s.description||'Apakah tanda ini terlihat?')}</small></span><select data-disease="${d.id}" data-symptom="${s.id}"><option value="tidak">Tidak</option><option value="tidak_yakin">Tidak yakin</option><option value="sedikit_yakin">Sedikit yakin</option><option value="cukup_yakin">Cukup yakin</option><option value="yakin">Yakin</option><option value="sangat_yakin">Sangat yakin</option></select></label>`).join(''):'<p class="muted">Belum ditambahkan gejala khusus untuk penyakit ini. Admin dapat mengelola aturan.</p>'}</section>`).join('')+'<div class="form-actions"><button class="button" id="calc">Lihat hasil diagnosis →</button></div>';
 document.querySelector('#calc').onclick=async()=>{const answers=[...area.querySelectorAll('select[data-symptom]')].map(s=>({disease_id:Number(s.dataset.disease),symptom_id:Number(s.dataset.symptom),value:s.value}));const rr=await fetch((window.KOPICARE_BASE||'')+'/api/diagnose.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({stage:'calculate',answers,general:window.generalIds})});const result=await rr.json();if(result.error){alert(result.error);return;}location.href=result.url;};area.scrollIntoView({behavior:'smooth'});
 });
});function esc(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

// Animate the confidence columns and labels when the chart enters the viewport.
document.addEventListener('DOMContentLoaded',()=>{
 const chart=document.querySelector('.bar-chart');
 if(!chart)return;
 let started=false;
 chart.querySelectorAll('.chart-column-bar').forEach(bar=>bar.style.height='0%');
 const animate=()=>{
  if(started)return;
  started=true;
  chart.querySelectorAll('.chart-column-bar').forEach((bar,index)=>{
   const value=Math.max(0,Math.min(100,Number(bar.dataset.height)||0));
   const label=bar.closest('.chart-column')?.querySelector('.chart-column-value');
   window.setTimeout(()=>{
    bar.style.height=value+'%';
    if(!label)return;
    const start=performance.now(),duration=1000;
    const tick=now=>{const progress=Math.min(1,(now-start)/duration);label.textContent=(value*progress).toFixed(1)+'%';if(progress<1)requestAnimationFrame(tick);};
    requestAnimationFrame(tick);
   },index*140);
  });
 };
 if(window.matchMedia('(prefers-reduced-motion: reduce)').matches){
  chart.querySelectorAll('.chart-column-bar').forEach(bar=>bar.style.height=Math.max(0,Math.min(100,Number(bar.dataset.height)||0))+'%');
  chart.querySelectorAll('.chart-column-value').forEach(label=>label.textContent=(Number(label.dataset.value)||0).toFixed(1)+'%');
  return;
 }
 if('IntersectionObserver'in window){const observer=new IntersectionObserver(entries=>{if(entries.some(entry=>entry.isIntersecting)){animate();observer.disconnect();}},{threshold:.15});observer.observe(chart);}else animate();
});