import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Import and register the theme controller
import ThemeController from './controllers/theme_controller.js';
app.register('theme', ThemeController);
