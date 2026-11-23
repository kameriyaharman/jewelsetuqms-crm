package com.jewelsetu.qtrack;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.pm.PackageManager;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.provider.MediaStore;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.GeolocationPermissions;
import android.webkit.PermissionRequest;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.Toast;

import androidx.core.content.ContextCompat;
import androidx.core.content.FileProvider;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import java.io.File;
import java.util.ArrayList;
import java.util.List;

/**
 * JEWELSETU Q-TRACK — Android app for the ERP at https://jewelsetuqms.in/erp/
 * A full-screen WebView with: session cookies kept, camera / gallery photo upload (incl. capture=),
 * GPS for factory check-in, PDF / Excel downloads with the login cookie, pull-to-refresh,
 * back button = browser back, offline screen, and outside links (WhatsApp, phone, email, maps) in their apps.
 */
public class MainActivity extends Activity {
    static final String HOST = "jewelsetuqms.in";
    static final String HOME = "https://" + HOST + "/erp/";
    static final int REQ_FILE = 11, REQ_PERM_CAMERA = 21, REQ_PERM_LOCATION = 22;

    WebView web; ProgressBar progress; View offline; SwipeRefreshLayout swipe;
    ValueCallback<Uri[]> fileCallback; WebChromeClient.FileChooserParams fileParams; Uri cameraUri;
    GeolocationPermissions.Callback geoCallback; String geoOrigin;
    final List<Long> openWhenDone = new ArrayList<>();

    @SuppressLint("SetJavaScriptEnabled")
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);
        setContentView(R.layout.activity_main);
        web = findViewById(R.id.web); progress = findViewById(R.id.progress); offline = findViewById(R.id.offline); swipe = findViewById(R.id.swipe);
        findViewById(R.id.retry).setOnClickListener(v -> { offline.setVisibility(View.GONE); web.reload(); });

        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setDatabaseEnabled(true);
        s.setGeolocationEnabled(true);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setAllowFileAccess(false);
        s.setLoadWithOverviewMode(true);
        s.setUseWideViewPort(true);
        s.setBuiltInZoomControls(true);
        s.setDisplayZoomControls(false);
        s.setSupportMultipleWindows(false);   // target=_blank opens in the same view (PDFs then download)
        s.setUserAgentString(s.getUserAgentString() + " JewelsetuQTrackApp/" + BuildConfigHelper.version(this));
        CookieManager cm = CookieManager.getInstance(); cm.setAcceptCookie(true); cm.setAcceptThirdPartyCookies(web, true);

        swipe.setColorSchemeColors(0xFF0F3D45, 0xFFC9A24B);
        swipe.setOnChildScrollUpCallback((parent, child) -> web.getScrollY() > 0);
        swipe.setOnRefreshListener(() -> web.reload());

        web.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest req) { return handleUrl(req.getUrl()); }
            @Override public void onPageFinished(WebView view, String url) {
                swipe.setRefreshing(false); CookieManager.getInstance().flush();
            }
            @Override public void onReceivedError(WebView view, WebResourceRequest req, WebResourceError err) {
                if (req.isForMainFrame()) { swipe.setRefreshing(false); offline.setVisibility(View.VISIBLE); }
            }
        });
        web.setWebChromeClient(new WebChromeClient() {
            @Override public void onProgressChanged(WebView view, int p) {
                progress.setProgress(p); progress.setVisibility(p < 100 ? View.VISIBLE : View.GONE);
            }
            @Override public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback cb) {
                if (hasLocation()) { cb.invoke(origin, true, true); return; }
                geoOrigin = origin; geoCallback = cb;
                requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION}, REQ_PERM_LOCATION);
            }
            @Override public void onPermissionRequest(PermissionRequest r) {   // getUserMedia (live camera) if a page ever uses it
                runOnUiThread(() -> { if (granted(Manifest.permission.CAMERA)) r.grant(r.getResources()); else r.deny(); });
            }
            @Override public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> cb, FileChooserParams params) {
                if (fileCallback != null) fileCallback.onReceiveValue(null);
                fileCallback = cb; fileParams = params;
                if (wantsImage(params) && !granted(Manifest.permission.CAMERA)) { requestPermissions(new String[]{Manifest.permission.CAMERA}, REQ_PERM_CAMERA); return true; }
                openChooser(); return true;
            }
        });
        web.setDownloadListener((url, ua, disposition, mime, length) -> download(url, ua, disposition, mime));

        ContextCompat.registerReceiver(this, doneReceiver, new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE), ContextCompat.RECEIVER_EXPORTED);

        if (state != null) web.restoreState(state);
        else {
            Uri deep = getIntent() != null ? getIntent().getData() : null;
            web.loadUrl(deep != null && HOST.equals(deep.getHost()) ? deep.toString() : HOME);
        }
        if (!online()) offline.setVisibility(View.VISIBLE);
    }

    @Override protected void onNewIntent(Intent i) {
        super.onNewIntent(i);
        Uri u = i.getData(); if (u != null && HOST.equals(u.getHost())) web.loadUrl(u.toString());
    }
    @Override protected void onSaveInstanceState(Bundle out) { super.onSaveInstanceState(out); web.saveState(out); }
    @Override protected void onPause() { super.onPause(); CookieManager.getInstance().flush(); }
    @Override protected void onDestroy() { try { unregisterReceiver(doneReceiver); } catch (Exception ignored) {} super.onDestroy(); }
    @SuppressWarnings("deprecation")
    @Override public void onBackPressed() {
        if (offline.getVisibility() == View.VISIBLE && web.canGoBack()) { offline.setVisibility(View.GONE); web.goBack(); }
        else if (web.canGoBack()) web.goBack(); else super.onBackPressed();
    }

    /** our site stays inside the app; everything else (WhatsApp, tel:, mailto:, maps, other sites) opens outside */
    boolean handleUrl(Uri u) {
        String scheme = u.getScheme() == null ? "" : u.getScheme();
        if (("https".equals(scheme) || "http".equals(scheme)) && HOST.equalsIgnoreCase(u.getHost())) return false;
        try {
            if ("intent".equals(scheme)) { startActivity(Intent.parseUri(u.toString(), Intent.URI_INTENT_SCHEME)); return true; }
            startActivity(new Intent(Intent.ACTION_VIEW, u));
        } catch (Exception e) { Toast.makeText(this, "No app found to open this link", Toast.LENGTH_SHORT).show(); }
        return true;
    }

    /* ---------- photo / file upload ---------- */
    boolean wantsImage(WebChromeClient.FileChooserParams p) {
        for (String t : p.getAcceptTypes()) if (t != null && (t.isEmpty() || t.startsWith("image"))) return true;
        return p.getAcceptTypes().length == 0;
    }
    void openChooser() {
        WebChromeClient.FileChooserParams p = fileParams; cameraUri = null;
        Intent camera = null;
        if (wantsImage(p) && granted(Manifest.permission.CAMERA)) {
            try {
                File dir = new File(getCacheDir(), "camera"); dir.mkdirs();
                File f = File.createTempFile("qc_", ".jpg", dir);
                cameraUri = FileProvider.getUriForFile(this, getPackageName() + ".files", f);
                camera = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
                camera.putExtra(MediaStore.EXTRA_OUTPUT, cameraUri);
                camera.addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION | Intent.FLAG_GRANT_READ_URI_PERMISSION);
            } catch (Exception e) { camera = null; cameraUri = null; }
        }
        Intent pick = p.createIntent();
        pick.addCategory(Intent.CATEGORY_OPENABLE);
        if (p.getMode() == WebChromeClient.FileChooserParams.MODE_OPEN_MULTIPLE) pick.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
        Intent go;
        if (camera != null && p.isCaptureEnabled()) go = camera;                 // <input capture> → straight to the camera
        else if (camera != null) { go = Intent.createChooser(pick, "Add photo"); go.putExtra(Intent.EXTRA_INITIAL_INTENTS, new Intent[]{camera}); }
        else go = pick;
        try { startActivityForResult(go, REQ_FILE); }
        catch (ActivityNotFoundException e) { fileCallback.onReceiveValue(null); fileCallback = null; Toast.makeText(this, "No camera / file app found", Toast.LENGTH_SHORT).show(); }
    }
    @Override protected void onActivityResult(int req, int res, Intent data) {
        super.onActivityResult(req, res, data);
        if (req != REQ_FILE || fileCallback == null) return;
        Uri[] out = null;
        if (res == RESULT_OK) {
            if (data != null && data.getClipData() != null) {
                int n = data.getClipData().getItemCount(); out = new Uri[n];
                for (int i = 0; i < n; i++) out[i] = data.getClipData().getItemAt(i).getUri();
            } else if (data != null && data.getData() != null) out = new Uri[]{data.getData()};
            else if (cameraUri != null) out = new Uri[]{cameraUri};
        }
        fileCallback.onReceiveValue(out); fileCallback = null;
    }

    /* ---------- permissions ---------- */
    boolean granted(String p) { return checkSelfPermission(p) == PackageManager.PERMISSION_GRANTED; }
    boolean hasLocation() { return granted(Manifest.permission.ACCESS_FINE_LOCATION) || granted(Manifest.permission.ACCESS_COARSE_LOCATION); }
    @Override public void onRequestPermissionsResult(int req, String[] perms, int[] res) {
        super.onRequestPermissionsResult(req, perms, res);
        if (req == REQ_PERM_CAMERA && fileCallback != null) openChooser();
        if (req == REQ_PERM_LOCATION && geoCallback != null) {
            boolean ok = hasLocation(); geoCallback.invoke(geoOrigin, ok, ok); geoCallback = null;
            if (!ok) Toast.makeText(this, "Allow location to check in at the factory", Toast.LENGTH_LONG).show();
        }
    }

    /* ---------- downloads (reports PDF, Excel exports) with the login cookie ---------- */
    void download(String url, String ua, String disposition, String mime) {
        try {
            String name = URLUtil.guessFileName(url, disposition, mime);
            DownloadManager.Request r = new DownloadManager.Request(Uri.parse(url));
            String cookie = CookieManager.getInstance().getCookie(url);
            if (cookie != null) r.addRequestHeader("Cookie", cookie);
            r.addRequestHeader("User-Agent", ua);
            r.setMimeType(mime); r.setTitle(name); r.setDescription("JEWELSETU Q-TRACK");
            r.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            r.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
            DownloadManager dm = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
            long id = dm.enqueue(r); openWhenDone.add(id);
            Toast.makeText(this, "Downloading " + name + "…", Toast.LENGTH_SHORT).show();
            if (web.canGoBack() && url.equals(web.getUrl())) web.goBack();
        } catch (Exception e) {
            startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
        }
    }
    final BroadcastReceiver doneReceiver = new BroadcastReceiver() {
        @Override public void onReceive(Context c, Intent i) {
            long id = i.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1);
            if (!openWhenDone.remove(id)) return;
            DownloadManager dm = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
            Uri u = dm.getUriForDownloadedFile(id); if (u == null) return;
            Intent v = new Intent(Intent.ACTION_VIEW); v.setDataAndType(u, dm.getMimeTypeForDownloadedFile(id));
            v.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
            try { startActivity(v); } catch (Exception e) { Toast.makeText(c, "Saved in Downloads", Toast.LENGTH_LONG).show(); }
        }
    };

    boolean online() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        @SuppressWarnings("deprecation") NetworkInfo n = cm == null ? null : cm.getActiveNetworkInfo();
        return n != null && n.isConnected();
    }

    static class BuildConfigHelper {
        static String version(Context c) {
            try { return c.getPackageManager().getPackageInfo(c.getPackageName(), 0).versionName; } catch (Exception e) { return "1"; }
        }
    }
}
