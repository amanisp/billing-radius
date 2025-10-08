 <li class="sidebar-item ">
     <a href="" onclick="alert('Comming Soon')" class='sidebar-link'>
         <i class="fa-solid fa-ethernet"></i>
         <span>OLT</span>
     </a>
 </li>
 <li
     class="sidebar-item has-sub {{ Route::is('members.index', 'pppoe.index', 'online.index', 'profiles.index', 'ppp.settings') ? 'active' : '' }}">
     <a href="#" class='sidebar-link'>
         <i class="fa-solid fa-server"></i>
         <span>Services</span>
     </a>

     <ul class="submenu ">
         <li class="submenu-item {{ Route::is('members.index') ? 'active' : '' }} ">
             <a href="{{ route('members.index') }}" class='submenu-link'>Member</a>
         </li>
         <li class="submenu-item {{ Route::is('pppoe.index') ? 'active' : '' }} ">
             <a href="{{ route('pppoe.index') }}" class='submenu-link'>PPPoE</a>
         </li>
         <li class="submenu-item {{ Route::is('online.index') ? 'active' : '' }} ">
             <a href="{{ route('online.index') }}" class='submenu-link'>Session</a>
         </li>
         <li class="submenu-item {{ Route::is('profiles.index') ? 'active' : '' }} ">
             <a href="{{ route('profiles.index') }}" class='submenu-link'>Profile</a>
         </li>
         <li class="submenu-item {{ Route::is('ppp.index') ? 'active' : '' }} ">

         <li class="submenu-item {{ Route::is('ppp.settings') ? 'active' : '' }} ">
             <a href="{{ route('ppp.settings') }}" class='submenu-link'>Setting</a>
         </li>
     </ul>
 </li>
 <li class="sidebar-item ">
     <a href="" onclick="alert('Comming Soon')" class='sidebar-link'>
         <i class="fa-solid fa-ticket"></i>
         <span>Tiket</span>
     </a>
 </li>
 <hr>
 <li class="sidebar-title">Billing</li>
 <li class="sidebar-item {{ Route::is('billing.invoice') ? 'active' : '' }}">
     <a href="{{ route('billing.invoice') }}" class='sidebar-link'>
         <i class="fa-solid fa-file-invoice-dollar"></i>
         <span>Invoices</span>
     </a>
 </li>
 <li class="sidebar-item {{ Route::is('billing.transaction') ? 'active' : '' }}">
     <a href="{{ route('billing.transaction') }}" class='sidebar-link'>
         <i class="fa-solid fa-cash-register"></i>
         <span>Transaction</span>
     </a>
 </li>
 <li class="sidebar-item {{ Route::is('billing.setting') ? 'active' : '' }}">
     <a href="{{ route('billing.setting') }}" class='sidebar-link'>
         <i class="fa-solid fa-gear"></i>
         <span>Setting & Withdraw</span>
     </a>
 </li>
 <li class="sidebar-title">Utility</li>
 <li class="sidebar-item has-sub {{ Route::is('optical.index', 'area.index') ? 'active' : '' }}">
     <a href="#" class='sidebar-link'>
         <i class="fa-solid fa-database"></i>
         <span>Master Data</span>
     </a>
     <ul class="submenu ">
         <li class="submenu-item  {{ Route::is('optical.index') ? 'active' : '' }}">
             <a href="{{ route('optical.index') }}" class="submenu-link">POP/ODP/ODC</a>
         </li>
         <li class="submenu-item  {{ Route::is('area.index') ? 'active' : '' }}">
             <a href="{{ route('area.index') }}" class="submenu-link">Area</a>
         </li>
     </ul>
 </li>
 <li class="sidebar-item has-sub {{ Route::is('vpn.index', 'radius.index') ? 'active' : '' }}">
     <a href="#" class='sidebar-link'>
         <i class="fa-solid fa-server"></i>
         <span>NAS</span>
     </a>
     <ul class="submenu ">
         <li class="submenu-item  {{ Route::is('vpn.index') ? 'active' : '' }}">
             <a href="{{ route('vpn.index') }}" class="submenu-link">VPN</a>
         </li>
         <li class="submenu-item  {{ Route::is('radius.index') ? 'active' : '' }}">
             <a href="{{ route('radius.index') }}" class="submenu-link">Radius</a>
         </li>
     </ul>
 </li>
 <li class="sidebar-item {{ Route::is('admin.index') ? 'active' : '' }} ">
     <a href="{{ route('admin.index') }}" class='sidebar-link '>
         <i class="fa-solid fa-user-shield"></i>
         <span>Admin</span>
     </a>
 </li>
 <li class="sidebar-item {{ Route::is('whatsapp.index') ? 'active' : '' }}">
     <a href="{{ route('whatsapp.index') }}" class='sidebar-link'>
         <i class="fa-brands fa-whatsapp"></i>
         <span>Whatsapp</span>
     </a>
 </li>
 <li class="sidebar-item  {{ Route::is('maps.index') ? 'active' : '' }}">
     <a href="{{ route('maps.index') }}" class='sidebar-link '>
         <i class="fa-solid fa-map-location-dot"></i>
         <span>Maps</span>
     </a>
 </li>
 <li class="sidebar-item {{ Route::is('logs.index') ? 'active' : '' }}">
     <a href="{{ route('logs.index') }}" class="sidebar-link">
         <i class="fa-solid fa-address-book"></i>
         <span>Logs</span>
     </a>
 </li>
 <hr>
 <li class="sidebar-title">Support</li>
 <li class="sidebar-item  ">
     <a href="https://docs.amanisp.net.id" target="_blank" class='sidebar-link'>
         <i class="fa-solid fa-file"></i>
         <span>Dokumentasi</span>
     </a>
 </li>
 <li class="sidebar-item  ">
     <a href="https://wa.me/6285175194507" target="_blank" class='sidebar-link'>
         <i class="fa-solid fa-headset"></i>
         <span>Helpdesk</span>
     </a>
 </li>
