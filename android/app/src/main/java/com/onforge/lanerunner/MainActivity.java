package com.onforge.lanerunner;

import android.os.Build;
import android.view.Display;
import android.view.WindowManager;

import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    // Fullscreen game: keep Android's navigation bar (3-button/gesture pill)
    // hidden. Sticky immersive — an edge swipe shows it translucently and it
    // auto-hides again after a moment. Re-applied on every focus gain so
    // dialogs/ads can't leave the bar stuck visible.
    @Override
    public void onWindowFocusChanged(boolean hasFocus) {
        super.onWindowFocusChanged(hasFocus);
        if (hasFocus) {
            hideNavigationBar();
            applyHighestRefreshRate();
        }
    }

    // Nubia/RedMagic and other non-Samsung OEMs hand a WebView window 60Hz even
    // when the system's 120Hz toggle is on — the high-rate Display.Mode is
    // opt-in per window. Samsung One UI applies it generously, which is why the
    // game hits 120Hz there with no effort. Explicitly request the highest
    // refresh-rate mode at the CURRENT resolution (resolution-only-differs modes
    // are skipped so there's no jarring/failing resolution switch). No-op on
    // Samsung — the best mode is already active there.
    private void applyHighestRefreshRate() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return;
        Display display = getWindow().getDecorView().getDisplay();
        if (display == null) return;
        Display.Mode current = display.getMode();
        Display.Mode best = current;
        for (Display.Mode m : display.getSupportedModes()) {
            if (m.getPhysicalWidth() == current.getPhysicalWidth()
                    && m.getPhysicalHeight() == current.getPhysicalHeight()
                    && m.getRefreshRate() > best.getRefreshRate()) {
                best = m;
            }
        }
        if (best.getModeId() != current.getModeId()) {
            WindowManager.LayoutParams params = getWindow().getAttributes();
            params.preferredDisplayModeId = best.getModeId();
            getWindow().setAttributes(params);
        }
    }

    private void hideNavigationBar() {
        WindowInsetsControllerCompat controller =
                WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        controller.hide(WindowInsetsCompat.Type.navigationBars());
        controller.setSystemBarsBehavior(
                WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE);
    }
}
