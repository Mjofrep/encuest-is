<?php
declare(strict_types=1);
?>
<style>
body{
  background:#f7f9fc;
  font-size:0.9rem;
  color:#212529;
}

.card{
  border:none;
  box-shadow:0 2px 4px rgba(0,0,0,.05);
}

.topbar{
  background:#fff;
  border-bottom:1px solid #dee2e6;
}

.form-control,
.form-select{
  border-radius:.85rem;
  border:1px solid #dee2e6;
}

.form-control:focus,
.form-select:focus{
  box-shadow:0 0 0 .2rem rgba(13,110,253,.15);
  border-color:#86b7fe;
}

.btn{
  border-radius:.85rem;
}

.section-title{
  color:#0d6efd;
  font-weight:600;
}

.kpi-card h3{
  margin:0;
  font-weight:700;
}

.badge-soft-success{
  background:rgba(25,135,84,.12);
  color:#198754;
  border:1px solid rgba(25,135,84,.12);
}

.badge-soft-danger{
  background:rgba(220,53,69,.12);
  color:#dc3545;
  border:1px solid rgba(220,53,69,.12);
}

.badge-soft-warning{
  background:rgba(255,193,7,.18);
  color:#856404;
  border:1px solid rgba(255,193,7,.18);
}

.badge-soft-secondary{
  background:rgba(108,117,125,.12);
  color:#6c757d;
  border:1px solid rgba(108,117,125,.12);
}

.score-option{
  width:48px;
  height:48px;
  border-radius:50%;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-weight:600;
}

.feedback-shell{
  max-width:760px;
  margin:auto;
}

.soft-box{
  background:#f8fafc;
  border:1px solid #e9ecef;
  border-radius:1rem;
  padding:1rem;
}

.table thead th{
  background:#f8f9fa;
  font-size:.85rem;
  color:#495057;
  vertical-align:middle;
}

.table td{
  vertical-align:middle;
}

.comment-preview{
  max-width:360px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

.stat-icon{
  width:42px;
  height:42px;
  border-radius:12px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(13,110,253,.08);
  color:#0d6efd;
  font-size:1.1rem;
}

.progress{
  height:14px;
  border-radius:999px;
  overflow:hidden;
  background:#e9ecef;
}

.hero-icon{
  font-size:3rem;
}

.word-cloud{
  line-height:1.2;
  justify-content:center;
  text-align:center;
  min-height:140px;
}

.word-cloud-item{
  display:inline-block;
  font-weight:600;
  opacity:.9;
  margin:2px 6px;
}

@media (max-width: 576px){
  .score-option{
    width:42px;
    height:42px;
  }
}
</style>
